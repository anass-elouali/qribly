import logging

from fastapi import FastAPI, HTTPException, status

from app.ranking import rank_offers
from app.schemas import HealthResponse, RankRequest, RankResponse


logger = logging.getLogger(__name__)


app = FastAPI(
    title="Qribly AI Service",
    version="0.2.0",
    description="Semantic search service for Qribly.",
)


@app.get("/health", response_model=HealthResponse, tags=["system"])
def health() -> HealthResponse:
    return HealthResponse(status="ok", service="qribly-ai")


@app.post("/rank", response_model=RankResponse, tags=["semantic-search"])
def rank(request: RankRequest) -> RankResponse:
    try:
        results = rank_offers(
            query=request.query,
            offers=request.offers,
            limit=request.limit,
        )
    except Exception as exception:
        logger.exception("Semantic ranking failed")
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="The semantic ranking service is temporarily unavailable.",
        ) from exception

    return RankResponse(results=results)
