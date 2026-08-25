from functools import lru_cache
import re
import unicodedata

import numpy as np
from sentence_transformers import SentenceTransformer

from app.schemas import OfferCandidate, RankedOffer


MODEL_NAME = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"
SEMANTIC_WEIGHT = 0.75
LEXICAL_WEIGHT = 0.25

STOP_WORDS = {
    "a",
    "au",
    "aux",
    "avec",
    "ce",
    "chez",
    "dans",
    "de",
    "des",
    "du",
    "en",
    "et",
    "je",
    "la",
    "le",
    "les",
    "ma",
    "mes",
    "mon",
    "ne",
    "pas",
    "plus",
    "pour",
    "sur",
    "un",
    "une",
}


@lru_cache(maxsize=1)
def get_model() -> SentenceTransformer:
    """Load the semantic-search model once and reuse it for every request."""
    return SentenceTransformer(MODEL_NAME)


def rank_offers(
    query: str,
    offers: list[OfferCandidate],
    limit: int,
) -> list[RankedOffer]:
    model = get_model()
    offer_texts = [offer.text for offer in offers]

    query_embedding = model.encode(query, normalize_embeddings=True)
    offer_embeddings = model.encode(offer_texts, normalize_embeddings=True)
    semantic_scores = np.dot(offer_embeddings, query_embedding)

    ranked_offers = [
        RankedOffer(
            id=offer.id,
            semantic_score=round(
                SEMANTIC_WEIGHT * float(semantic_score)
                + LEXICAL_WEIGHT * _lexical_score(query, offer.text),
                4,
            ),
        )
        for offer, semantic_score in zip(offers, semantic_scores, strict=True)
    ]

    return sorted(
        ranked_offers,
        key=lambda offer: offer.semantic_score,
        reverse=True,
    )[:limit]


def _lexical_score(query: str, offer_text: str) -> float:
    query_tokens = _significant_tokens(query)
    offer_tokens = _significant_tokens(offer_text)

    if not query_tokens or not offer_tokens:
        return 0.0

    query_set = set(query_tokens)
    offer_set = set(offer_tokens)
    token_overlap = len(query_set & offer_set) / len(query_set)

    query_bigrams = set(zip(query_tokens, query_tokens[1:]))
    offer_bigrams = set(zip(offer_tokens, offer_tokens[1:]))
    phrase_bonus = 0.25 if query_bigrams & offer_bigrams else 0.0

    return min(1.0, token_overlap + phrase_bonus)


def _significant_tokens(text: str) -> list[str]:
    normalized = unicodedata.normalize("NFKD", text.lower())
    normalized = "".join(
        character for character in normalized if not unicodedata.combining(character)
    )

    return [
        token
        for token in re.findall(r"[a-z0-9]+", normalized)
        if len(token) >= 3 and token not in STOP_WORDS
    ]
