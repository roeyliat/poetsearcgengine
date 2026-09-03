# POET Therapist Directory

Standalone Hebrew/RTL directory for reviewing and locating occupational therapists certified in the POET approach.

## Review locally

The site loads its data with `fetch`, so open it through a local web server rather than directly as a `file://` URL:

```bash
python3 -m http.server 8080 --directory public
```

Then visit <http://localhost:8080>.

## Update the data

1. Edit the source in Google Sheets.
2. Download the sheet as CSV.
3. Run:

```bash
python3 scripts/clean_poet_excel.py \
  --input "/path/to/export.csv" \
  --outdir data
```

The script validates and normalizes the records and automatically updates `public/data/therapists.json`.

## Handoff and embedding

The `public` directory is a complete static site with no runtime dependencies or database.

After approval it can be integrated into the parent site in either of these ways:

- publish `public` on a subdomain and embed it with an iframe;
- copy the directory markup, CSS, JavaScript, and JSON into the parent WordPress site.

The second option gives the best SEO and visual integration. The first keeps deployment and maintenance isolated.

## Public data

The generated JSON intentionally includes the therapist contact details displayed on the public cards. Do not add private or internal fields to the source sheet.
