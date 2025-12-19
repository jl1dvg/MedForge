from pathlib import Path
import unittest

from scrapping.perfex_estimates_scraper import parse_perfex_estimates_page


FIXTURE = Path(__file__).resolve().parent / "sample_perfex_estimates.html"


class PerfexEstimatesScraperTest(unittest.TestCase):
    def setUp(self) -> None:
        self.html = FIXTURE.read_text(encoding="utf-8")
        self.parsed = parse_perfex_estimates_page(self.html)

    def test_summary_cards_are_parsed(self):
        summary = self.parsed["summary"]
        self.assertEqual(len(summary), 5)
        self.assertEqual(
            summary[0],
            {"label": "Draft", "percentage": 40.0, "count": 4, "total": 10},
        )
        self.assertEqual(
            summary[1],
            {"label": "Sent", "percentage": 40.0, "count": 4, "total": 10},
        )

    def test_estimate_rows_are_extracted(self):
        estimates = self.parsed["estimates"]
        self.assertEqual(len(estimates), 10)

        first = estimates[0]
        self.assertEqual(first["estimate_number"], "EST-000010")
        self.assertEqual(first["amount"], 250.0)
        self.assertEqual(first["total_tax"], 0.0)
        self.assertEqual(first["customer"], "Berge, D'Amore and Stracke")
        self.assertEqual(first["date"], "2025-12-18")
        self.assertEqual(first["expiry_date"], "2026-01-08")
        self.assertEqual(first["status"], "Declined")

    def test_totals_by_status(self):
        totals = self.parsed["totals"]
        self.assertEqual(totals["estimates"], 10)
        self.assertEqual(totals["by_status"]["Draft"], 4)
        self.assertEqual(totals["by_status"]["Sent"], 4)
        self.assertEqual(totals["by_status"]["Declined"], 2)


if __name__ == "__main__":
    unittest.main()
