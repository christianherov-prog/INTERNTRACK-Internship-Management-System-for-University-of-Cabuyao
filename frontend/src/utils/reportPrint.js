/**
 * Shared print CSS for InternTrack generic reports (not official form templates).
 *
 * Column sizing: cells use `overflow-wrap: break-word`, which only breaks a word
 * that cannot fit on its own line. The previous `word-break: break-word` let the
 * table's min-content width collapse to single characters, so auto layout
 * squeezed short columns and printed "HOUR/S", "DAY/S" and "240/50/0".
 * Short headings and numeric cells stay on one line; long text columns
 * (Student, Program, Company) absorb the remaining width and wrap by word.
 */
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
      font-size: 10.5px !important;
      border-collapse: collapse;
    }
    thead {
      display: table-header-group;
    }
    th, td {
      word-break: normal;
      overflow-wrap: break-word;
      padding: 4px 6px !important;
      vertical-align: top;
    }
    th {
      white-space: nowrap;
      font-size: 9px !important;
      letter-spacing: 0.02em !important;
      line-height: 1.25;
      vertical-align: bottom;
    }
    .it-col-num,
    .it-col-nowrap {
      white-space: nowrap;
      width: 1%;
    }
    .it-col-text {
      min-width: 34mm;
    }
    .it-col-progress {
      min-width: 22mm;
      width: 1%;
    }
    .it-col-status {
      white-space: nowrap;
      width: 1%;
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
