from functools import lru_cache

import numpy as np
from sentence_transformers import SentenceTransformer

from app.schemas import OfferCandidate, RankedOffer


MODEL_NAME = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"


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
    scores = np.dot(offer_embeddings, query_embedding)

    ranked_offers = [
        RankedOffer(
            id=offer.id,
            semantic_score=round(float(score), 4),
        )
        for offer, score in zip(offers, scores, strict=True)
    ]

    return sorted(
        ranked_offers,
        key=lambda offer: offer.semantic_score,
        reverse=True,
    )[:limit]
