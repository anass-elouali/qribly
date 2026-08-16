from pydantic import BaseModel, ConfigDict, Field


class HealthResponse(BaseModel):
    status: str
    service: str


class OfferCandidate(BaseModel):
    id: int = Field(..., ge=1, examples=[1])
    text: str = Field(
        ...,
        min_length=3,
        max_length=5_000,
        examples=["Dépannage et réparation de smartphones Android et iPhone."],
    )


class RankRequest(BaseModel):
    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "query": "réparer téléphone",
                    "offers": [
                        {
                            "id": 1,
                            "text": "Cours de français pour débutants à Marrakech.",
                        },
                        {
                            "id": 2,
                            "text": "Dépannage et réparation de smartphones Android et iPhone.",
                        },
                    ],
                    "limit": 2,
                }
            ]
        }
    )

    query: str = Field(..., min_length=2, max_length=500)
    offers: list[OfferCandidate] = Field(..., min_length=1, max_length=50)
    limit: int = Field(default=10, ge=1, le=50)


class RankedOffer(BaseModel):
    id: int
    semantic_score: float


class RankResponse(BaseModel):
    results: list[RankedOffer]
