/** Shared print CSS for InternTrack generic reports (not official form templates). */
export const REPORT_PRINT_PAGE_STYLE = `
  @page {
    size: A4 landscape;
    margin: 12mm;
  }
  @media print {
    html, body {
      width: 297mm;
      height: 210mm;
    }
    table {
      width: 100% !important;
      table-layout: auto;
      font-size: 11px !important;
    }
    th, td {
      word-break: break-word;
    }
    .progress {
      print-color-adjust: exact;
      -webkit-print-color-adjust: exact;
    }
    tr {
      break-inside: avoid;
    }
  }
`

export function reportPrintOptions(contentRef, documentTitle) {
  return {
    contentRef,
    documentTitle,
    pageStyle: REPORT_PRINT_PAGE_STYLE,
  }
}
