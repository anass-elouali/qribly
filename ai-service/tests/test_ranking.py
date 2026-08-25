import unittest
from unittest.mock import MagicMock, patch

import numpy as np

from app.ranking import _lexical_score, rank_offers
from app.schemas import OfferCandidate


class OfferRankingTest(unittest.TestCase):
    def test_lexical_score_rewards_explicit_service_words_and_phrases(self) -> None:
        query = "Mon ordinateur portable ne démarre plus à Casablanca."

        computer_score = _lexical_score(
            query,
            "Réparation d’ordinateur portable : démarrage, batterie et logiciel.",
        )
        smartphone_score = _lexical_score(
            query,
            "Réparation de smartphone : écran, batterie et problème de charge.",
        )

        self.assertGreater(computer_score, smartphone_score)

    @patch("app.ranking.get_model")
    def test_hybrid_score_corrects_a_close_semantic_false_positive(
        self,
        get_model,
    ) -> None:
        model = MagicMock()
        model.encode.side_effect = [
            np.array([1.0, 0.0]),
            np.array([
                [0.35, 0.0],
                [0.38, 0.0],
            ]),
        ]
        get_model.return_value = model

        results = rank_offers(
            "Mon ordinateur portable ne démarre plus à Casablanca.",
            [
                OfferCandidate(
                    id=1,
                    text="Réparation d’ordinateur portable : démarrage et batterie.",
                ),
                OfferCandidate(
                    id=2,
                    text="Réparation de smartphone : écran, batterie et charge.",
                ),
            ],
            2,
        )

        self.assertEqual([1, 2], [result.id for result in results])
        self.assertGreater(results[0].semantic_score, results[1].semantic_score)


if __name__ == "__main__":
    unittest.main()
