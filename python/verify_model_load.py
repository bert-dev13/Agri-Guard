"""Smoke test: load xgboost_weather_model.pkl (run with project .venv Python)."""
from __future__ import annotations

import sys
import os
from pathlib import Path


def main() -> int:
    root = Path(__file__).resolve().parent
    model_path = Path(
        os.environ.get(
            "AGRIWEATHER_MODEL_PATH",
            str(root / "model" / "xgboost_weather_model.pkl"),
        )
    )
    if not model_path.is_file():
        print(f"ERROR: missing model at {model_path}", file=sys.stderr)
        return 1
    try:
        import joblib  # noqa: PLC0415
    except ImportError as exc:
        print(f"ERROR: joblib import failed: {exc}", file=sys.stderr)
        return 2
    try:
        joblib.load(model_path)
    except Exception as exc:  # noqa: BLE001
        print(f"ERROR: load failed: {exc}", file=sys.stderr)
        return 3
    print("MODEL_LOAD_OK", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
