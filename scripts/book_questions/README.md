# Book Questions PDF Extraction

Book-question generation extracts PDF text locally before sending chapter text to AI.

## Runtime Tools

The local server needs Poppler tools:

```bash
pdfinfo
pdftotext
```

In Docker these are installed through `poppler-utils` in the project `Dockerfile`.

## Flow

- `BookChapterExtractor` gets page count with `pdfinfo`.
- It extracts only the selected chapter pages with `pdftotext`.
- `BookQuestionGenerator` sends the extracted chapter text to Gemini for question generation.
- The full PDF is not sent to Gemini for page counting or text extraction.
