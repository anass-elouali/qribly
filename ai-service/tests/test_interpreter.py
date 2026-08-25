import json
import os
import unittest
from datetime import datetime, timedelta, timezone
from decimal import Decimal
from unittest.mock import MagicMock, patch

import httpx

from app.interpreter import (
    LlmSettings,
    LlmInterpretationDraft,
    _finalize_interpretation,
    _interpret_with_llm,
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
    @patch("app.interpreter._interpret_with_llm")
    def test_openai_failure_falls_back_to_the_local_interpreter(
        self,
        interpret_with_llm,
        classify_category,
    ) -> None:
        interpret_with_llm.side_effect = httpx.ConnectError("unavailable")
        classify_category.return_value = self.categories[0]

        result = interpret_service_request(
            self.request("Je cherche un plombier à Rabat demain à domicile.")
        )

        self.assertEqual("local", result.meta.interpreter)
        self.assertEqual("Rabat", result.data.city)

    @patch.dict(
        os.environ,
        {
            "AI_PROVIDER": "groq",
            "GROQ_API_KEY": "test-groq-key",
        },
        clear=True,
    )
    @patch("app.interpreter._interpret_with_llm")
    def test_groq_configuration_uses_the_groq_interpreter(
        self,
        interpret_with_llm,
    ) -> None:
        interpret_with_llm.return_value = LlmInterpretationDraft(
            summary="Réparer une fuite à domicile à Rabat demain.",
            category_id=1,
            category_name="Services à domicile",
            city="Rabat",
            desired_start_at=self.now + timedelta(days=1),
            desired_end_at=self.now + timedelta(days=1, hours=2),
            budget_max=Decimal("300"),
            at_home=True,
        )

        result = interpret_service_request(
            self.request("Je cherche un plombier à Rabat demain à domicile.")
        )

        settings = interpret_with_llm.call_args.args[1]
        self.assertEqual("groq", settings.provider)
        self.assertEqual("openai/gpt-oss-20b", settings.model)
        self.assertEqual("https://api.groq.com/openai/v1", settings.base_url)
        self.assertEqual("groq", result.meta.interpreter)

    @patch("app.interpreter.httpx.Client")
    def test_groq_request_omits_unsupported_openai_fields(self, client_class) -> None:
        response = MagicMock()
        response.json.return_value = {
            "output": [
                {
                    "type": "message",
                    "content": [
                        {
                            "type": "output_text",
                            "text": json.dumps(
                                {
                                    "summary": "Réparer une fuite à domicile à Rabat demain.",
                                    "category_id": 1,
                                    "category_name": "Services à domicile",
                                    "city": "Rabat",
                                    "desired_start_at": "2026-08-25T08:00:00Z",
                                    "desired_end_at": "2026-08-25T10:00:00Z",
                                    "budget_max": 300,
                                    "at_home": True,
                                }
                            ),
                        }
                    ],
                }
            ]
        }
        client = client_class.return_value.__enter__.return_value
        client.post.return_value = response
        settings = LlmSettings(
            provider="groq",
            api_key="test-groq-key",
            model="openai/gpt-oss-20b",
            base_url="https://api.groq.com/openai/v1",
            timeout_seconds=20,
        )

        _interpret_with_llm(
            self.request("Je cherche un plombier à Rabat demain à domicile."),
            settings,
        )

        request_body = client.post.call_args.kwargs["json"]
        self.assertNotIn("store", request_body)
        self.assertNotIn("safety_identifier", request_body)
        self.assertEqual({"effort": "low"}, request_body["reasoning"])
        request_context = json.loads(request_body["input"])
        self.assertIn(
            "plomberie",
            request_context["allowed_categories"][0]["examples"],
        )
        self.assertTrue(request_body["text"]["format"]["strict"])
        schema = request_body["text"]["format"]["schema"]
        self.assertNotIn("minLength", schema["properties"]["summary"])
        self.assertNotIn(
            "format",
            schema["properties"]["desired_start_at"]["anyOf"][0],
        )

    @patch.dict(
        os.environ,
        {
            "AI_PROVIDER": "groq",
            "GROQ_API_KEY": "test-groq-key",
        },
        clear=True,
    )
    @patch("app.interpreter._classify_category")
    @patch("app.interpreter._interpret_with_llm")
    def test_groq_failure_falls_back_to_the_local_interpreter(
        self,
        interpret_with_llm,
        classify_category,
    ) -> None:
        interpret_with_llm.side_effect = httpx.ConnectError("unavailable")
        classify_category.return_value = self.categories[0]

        result = interpret_service_request(
            self.request("Je cherche un plombier à Rabat demain à domicile.")
        )

        self.assertEqual("local", result.meta.interpreter)
        self.assertEqual("Rabat", result.data.city)

    def test_finalization_ignores_llm_at_home_guess_without_explicit_textual_evidence(
        self,
    ) -> None:
        request = self.request(
            "Je cherche une coiffeuse à Rabat demain matin pour un mariage."
        )
        draft = LlmInterpretationDraft(
            summary="Réserver une coiffeuse à Rabat demain matin pour un mariage.",
            category_id=1,
            category_name="Services à domicile",
            city="Rabat",
            desired_start_at=self.now + timedelta(days=1),
            desired_end_at=self.now + timedelta(days=1, hours=2),
            budget_max=None,
            at_home=False,
        )

        result = _finalize_interpretation(request, draft)

        self.assertIsNone(result.at_home)
        self.assertIn("at_home", result.missing_fields)
        self.assertIn(
            "Le prestataire doit-il se déplacer à domicile ?",
            result.questions,
        )

    def test_finalization_trusts_the_local_extractor_over_a_conflicting_llm_guess(
        self,
    ) -> None:
        request = self.request(
            "Je cherche un plombier à Rabat demain, je passerai chez le prestataire."
        )
        draft = LlmInterpretationDraft(
            summary="Réparer une fuite chez le prestataire à Rabat demain.",
            category_id=1,
            category_name="Services à domicile",
            city="Rabat",
            desired_start_at=self.now + timedelta(days=1),
            desired_end_at=self.now + timedelta(days=1, hours=2),
            budget_max=None,
            at_home=True,
        )

        result = _finalize_interpretation(request, draft)

        self.assertFalse(result.at_home)
        self.assertEqual([], result.missing_fields)

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
