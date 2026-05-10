from __future__ import annotations

import json
import sys
import base64
import os
import warnings
from datetime import date, timedelta
from pathlib import Path
from typing import Any


FEATURE_COLUMNS: tuple[str, ...] = (
    "year",
    "month",
    "day",
    "wind_direction",
    "rainfall_lag1",
    "rainfall_lag2",
    "wind_lag1",
    "wind_lag2",
    "rainfall_avg3",
    "wind_avg3",
)


def emit(payload: dict[str, Any], exit_code: int = 0) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=False))
    sys.stdout.flush()
    raise SystemExit(exit_code)


def as_float(input_data: dict[str, Any], key: str) -> float:
    value = input_data.get(key)
    if value is None:
        raise ValueError(f"Missing required feature: {key}")
    return float(value)


def extract_model_performance(model: Any, model_path: Path) -> dict[str, Any]:
    metrics_sources: list[Path] = [
        model_path.with_suffix(".meta.json"),
        model_path.with_suffix(".metrics.json"),
    ]

    metadata: dict[str, Any] = {}
    for metrics_path in metrics_sources:
        if metrics_path.is_file():
            try:
                loaded = json.loads(metrics_path.read_text(encoding="utf-8"))
                if isinstance(loaded, dict):
                    metadata = loaded
                    break
            except Exception:
                # Keep inference resilient even if metadata file is malformed.
                pass

    if not metadata:
        for attr in ("model_performance", "metrics", "metadata"):
            value = getattr(model, attr, None)
            if isinstance(value, dict):
                metadata = value
                break

    def as_metric_float(key: str) -> float | None:
        value = metadata.get(key)
        return float(value) if isinstance(value, (int, float)) else None

    overall = as_metric_float("overall_accuracy")
    rainfall_r2 = as_metric_float("rainfall_r2")
    wind_r2 = as_metric_float("wind_r2")
    dataset_raw = metadata.get("dataset")
    dataset = dataset_raw if isinstance(dataset_raw, str) and dataset_raw.strip() else "Unknown"

    if isinstance(overall, float):
        confidence = "High" if overall >= 80.0 else ("Medium" if overall >= 60.0 else "Low")
    else:
        confidence = "Unknown"

    def r2_label(value: float | None) -> str:
        if not isinstance(value, float):
            return "Unknown"
        if value >= 0.8:
            return "Strong"
        if value >= 0.6:
            return "Good"
        return "Developing"

    return {
        "overall_accuracy": overall,
        "rainfall_r2": rainfall_r2,
        "wind_r2": wind_r2,
        "confidence": confidence,
        "rainfall_label": r2_label(rainfall_r2),
        "wind_label": r2_label(wind_r2),
        "dataset": dataset,
        "source": "python_model_metadata",
    }


def main() -> None:
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")
    warnings.filterwarnings("ignore")

    if len(sys.argv) < 2:
        emit({"status": "error", "message": "Missing JSON input argument."}, 2)

    raw_arg = sys.argv[1]
    try:
        # Prefer direct JSON. If shell escaping corrupted quotes, accept base64-transported JSON.
        try:
            input_data = json.loads(raw_arg)
        except Exception:
            decoded_arg = base64.b64decode(raw_arg).decode("utf-8")
            input_data = json.loads(decoded_arg)
        if not isinstance(input_data, dict):
            raise ValueError("Input payload must be a JSON object.")
    except Exception as exc:  # noqa: BLE001
        emit({"status": "error", "message": f"Invalid JSON input: {exc}"}, 2)

    base_dir = Path(__file__).resolve().parent
    model_path = Path(
        os.environ.get(
            "AGRIWEATHER_MODEL_PATH",
            str(base_dir / "model" / "xgboost_weather_model.pkl"),
        )
    )
    if not model_path.is_file():
        emit({"status": "error", "message": f"Model file not found: {model_path}"}, 3)

    try:
        import joblib  # noqa: PLC0415
        import numpy as np  # noqa: PLC0415
    except Exception as exc:  # noqa: BLE001
        emit({"status": "error", "message": f"Dependency import failed: {exc}"}, 4)

    try:
        model = joblib.load(str(model_path))
    except Exception as exc:  # noqa: BLE001
        emit({"status": "error", "message": f"Model load failed: {exc}"}, 5)

    model_performance = extract_model_performance(model, model_path)

    try:
        row = {column: as_float(input_data, column) for column in FEATURE_COLUMNS}
        start_date = date(int(row["year"]), int(row["month"]), int(row["day"]))
        forecast: list[dict[str, Any]] = []
        current = dict(row)

        feature_cols = list(FEATURE_COLUMNS)
        for day_index in range(1, 6):
            row_vec = np.array([[float(current[c]) for c in feature_cols]], dtype=np.float64)
            predicted = model.predict(row_vec)
            prediction_row = predicted[0]
            rainfall = float(prediction_row[0])
            wind_speed = float(prediction_row[1])

            day_date = start_date + timedelta(days=day_index - 1)
            forecast.append(
                {
                    "day": day_index,
                    "date": day_date.isoformat(),
                    "rainfall": rainfall,
                    "wind_speed": wind_speed,
                }
            )

            current["year"] = float(day_date.year)
            current["month"] = float(day_date.month)
            current["day"] = float(day_date.day)
            current["rainfall_lag2"] = float(current["rainfall_lag1"])
            current["wind_lag2"] = float(current["wind_lag1"])
            current["rainfall_lag1"] = rainfall
            current["wind_lag1"] = wind_speed
            current["rainfall_avg3"] = (float(current["rainfall_avg3"]) + rainfall) / 2.0
            current["wind_avg3"] = (float(current["wind_avg3"]) + wind_speed) / 2.0
    except Exception as exc:  # noqa: BLE001
        emit({"status": "error", "message": f"Prediction failed: {exc}"}, 6)

    emit(
        {
            "status": "success",
            "forecast": forecast,
            "model_performance": model_performance,
        }
    )


if __name__ == "__main__":
    main()
