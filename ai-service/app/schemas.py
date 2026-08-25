from datetime import datetime
from decimal import Decimal
from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, model_validator


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


class CategoryOption(BaseModel):
    id: int = Field(..., ge=1)
    name: str = Field(..., min_length=2, max_length=100)


class InterpretServiceRequest(BaseModel):
    raw_text: str = Field(..., min_length=10, max_length=2_000)
    categories: list[CategoryOption] = Field(..., min_length=1, max_length=50)
    cities: list[str] = Field(..., min_length=1, max_length=100)
    current_time: datetime
    safety_identifier: str | None = Field(default=None, pattern=r"^[a-f0-9]{64}$")


class InterpretationData(BaseModel):
    summary: str = Field(..., min_length=10, max_length=1_000)
    category_id: int | None = None
    category_name: str | None = None
    city: str | None = None
    desired_start_at: datetime | None = None
    desired_end_at: datetime | None = None
    budget_max: Decimal | None = Field(default=None, ge=0)
    at_home: bool | None = None
    missing_fields: list[
        Literal["category_id", "city", "desired_period", "at_home"]
    ] = Field(default_factory=list, max_length=4)
    questions: list[str] = Field(default_factory=list, max_length=2)

    @model_validator(mode="after")
    def validate_paired_values(self) -> "InterpretationData":
        if (self.category_id is None) != (self.category_name is None):
            raise ValueError("category_id and category_name must be provided together")

        if (self.desired_start_at is None) != (self.desired_end_at is None):
            raise ValueError("desired dates must be provided together")

        if (
            self.desired_start_at is not None
            and self.desired_end_at is not None
            and self.desired_end_at <= self.desired_start_at
        ):
            raise ValueError("desired_end_at must be after desired_start_at")

        return self


class InterpretationMeta(BaseModel):
    interpreter: Literal["local", "openai"]


class InterpretServiceResponse(BaseModel):
    data: InterpretationData
    meta: InterpretationMeta
