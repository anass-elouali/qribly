import os
import unittest
from datetime import datetime, timedelta, timezone
from decimal import Decimal
from unittest.mock import patch

import httpx

from app.interpreter import (
    LlmInterpretationDraft,
    _finalize_interpretation,
    interpret_service_request,
)
from app.schemas import CategoryOption, InterpretServiceRequest


class ServiceRequestInterpreterTest(unittest.TestCase):
    def setUp(self) -> None:
        self.now = datetime(2026, 8, 24, 6, 0, tzinfo=timezone.utc)
        self.categories = [
            CategoryOption(id=1, name="Services à domicile"),
            CategoryOption(id=2, name="Éducation"),
        ]
        self.cities = ["Fès", "Marrakech", "Rabat"]

    def request(self, raw_text: str) -> InterpretServiceRequest:
        return InterpretServiceRequest(
            raw_text=raw_text,
            categories=self.categories,
            cities=self.cities,
            current_time=self.now,
            safety_identifier="a" * 64,
        )

    @patch.dict(os.environ, {}, clear=True)
    @patch("app.interpreter._classify_category")
    def test_extracts_a_complete_french_request(self, classify_category) -> None:
        classify_category.return_value = self.categories[0]

        result = interpret_service_request(
            self.request(
                "Je cherche un plombier à Marrakech aujourd'hui à 18h, "
                "budget 300 DH, pour mon appartement."
            )
        )

        self.assertEqual("local", result.meta.interpreter)
        self.assertEqual(1, result.data.category_id)
        self.assertEqual("Marrakech", result.data.city)
        self.assertEqual(Decimal("300"), result.data.budget_max)
        self.assertTrue(result.data.at_home)
        self.assertEqual([], result.data.missing_fields)
        self.assertEqual(17, result.data.desired_start_at.hour)

    @patch.dict(os.environ, {}, clear=True)
    @patch("app.interpreter._classify_category")
    def test_understands_a_darija_request_without_inventing_missing_data(
        self,
        classify_category,
    ) -> None:
        classify_category.return_value = self.categories[1]

        result = interpret_service_request(
            self.request(
                "Bghit chi wahed y9ri weldi math f Rabat ghda, budget 200 dh."
            )
        )

        self.assertEqual(2, result.data.category_id)
        self.assertEqual("Rabat", result.data.city)
        self.assertEqual(Decimal("200"), result.data.budget_max)
        self.assertIsNone(result.data.at_home)
        self.assertEqual(["at_home"], result.data.missing_fields)
        self.assertEqual(1, len(result.data.questions))
        self.assertEqual(
            (self.now + timedelta(days=1)).date(),
            result.data.desired_start_at.date(),
        )

    @patch.dict(os.environ, {}, clear=True)
    @patch("app.interpreter._classify_category")
    def test_asks_at_most_two_questions_for_an_incomplete_request(
        self,
        classify_category,
    ) -> None:
        classify_category.return_value = self.categories[0]

        result = interpret_service_request(
            self.request("Je cherche un plombier disponible rapidement.")
        )

        self.assertEqual(
            ["city", "desired_period", "at_home"],
            result.data.missing_fields,
        )
        self.assertEqual(2, len(result.data.questions))

    @patch.dict(os.environ, {}, clear=True)
    @patch("app.interpreter._classify_category")
    def test_explicit_future_date_without_time_uses_the_requested_day(
        self,
        classify_category,
    ) -> None:
        classify_category.return_value = self.categories[0]

        result = interpret_service_request(
            self.request(
                "Je cherche un plombier à Fès le 29/08/2026 pour ma maison."
            )
        )

        self.assertEqual(datetime(2026, 8, 29).date(), result.data.desired_start_at.date())
        self.assertEqual(datetime(2026, 8, 29).date(), result.data.desired_end_at.date())

    @patch.dict(
        os.environ,
        {"OPENAI_API_KEY": "test-key", "OPENAI_MODEL": "test-model"},
        clear=True,
    )
    @patch("app.interpreter._classify_category")
    @patch("app.interpreter._interpret_with_openai")
    def test_openai_failure_falls_back_to_the_local_interpreter(
        self,
        interpret_with_openai,
        classify_category,
    ) -> None:
        interpret_with_openai.side_effect = httpx.ConnectError("unavailable")
        classify_category.return_value = self.categories[0]

        result = interpret_service_request(
            self.request("Je cherche un plombier à Rabat demain à domicile.")
        )

        self.assertEqual("local", result.meta.interpreter)
        self.assertEqual("Rabat", result.data.city)

    def test_rejects_category_and_city_values_outside_the_allowed_lists(self) -> None:
        request = self.request(
            "Je cherche un plombier à Rabat demain pour mon appartement."
        )
        draft = LlmInterpretationDraft(
            summary="Réparer une fuite demain à domicile.",
            category_id=999,
            category_name="Catégorie inventée",
            city="Ville inventée",
            desired_start_at=self.now + timedelta(days=1),
            desired_end_at=self.now + timedelta(days=1, hours=2),
            budget_max=Decimal("250"),
            at_home=True,
        )

        result = _finalize_interpretation(request, draft)

        self.assertIsNone(result.category_id)
        self.assertIsNone(result.category_name)
        self.assertIsNone(result.city)
        self.assertIn("category_id", result.missing_fields)
        self.assertIn("city", result.missing_fields)

    @patch.dict(
        os.environ,
        {
            "OPENAI_API_KEY": "test-key",
            "OPENAI_MODEL": "test-model",
            "OPENAI_TIMEOUT_SECONDS": "invalid",
        },
        clear=True,
    )
    @patch("app.interpreter._classify_category")
    def test_invalid_openai_configuration_falls_back_locally(
        self,
        classify_category,
    ) -> None:
        classify_category.return_value = self.categories[0]

        result = interpret_service_request(
            self.request("Je cherche un plombier à Rabat demain à domicile.")
        )

        self.assertEqual("local", result.meta.interpreter)


if __name__ == "__main__":
    unittest.main()
