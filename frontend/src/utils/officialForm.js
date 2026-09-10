import api from '../services/api'

export async function fetchOfficialForm(internshipId) {
  const { data } = await api.get(`/official-forms/${internshipId}`)
  return data
}

export function fo30PreviewData(bundle = {}) {
  const fo30 = bundle.fo30 || {}
  const identity = bundle.identity || {}
  return {
    studentName: fo30.student_name || identity.student_name || '',
    program: fo30.program || identity.program || '',
    companyName: fo30.company_name || identity.company_name || '',
    companyLogoPath: fo30.company_logo_path || bundle.company_logo_path || identity.company_logo_path || '',
    supervisorName: fo30.supervisor_name || identity.supervisor_name || '',
    studentSignaturePath: fo30.student_signature_path || identity.student_signature_path || '',
    supervisorSignaturePath: fo30.supervisor_signature_path || identity.supervisor_signature_path || '',
    logs: fo30.logs || bundle.attendance || [],
  }
}

export function fo31PreviewData(bundle = {}, journal = {}) {
  const identity = bundle.identity || {}
  return {
    studentName: journal.studentName || journal.student_name || identity.student_name || '',
    program: journal.program || journal.program_name || identity.program || '',
    companyName: journal.companyName || journal.company_name || identity.company_name || '',
    companyLogoPath: journal.companyLogoPath || journal.company_logo_path || bundle.company_logo_path || identity.company_logo_path || '',
    studentSignaturePath: journal.studentSignaturePath || journal.student_signature_path || identity.student_signature_path || '',
    weekNumber: journal.weekNumber ?? journal.week_number ?? journal.week ?? journal.entry_number,
    date: journal.date,
    endDate: journal.endDate || journal.end_date,
    accomplishment: journal.accomplishment || journal.activities_summary || '',
    difficulties: journal.difficulties || journal.challenges || '',
    insights: journal.insights || journal.learnings || '',
  }
}

export async function downloadOfficialPdf(kind, internshipId, filename, extraParams = {}) {
  const endpoint = kind === 'dtr'
    ? `/official-forms/${internshipId}/dtr.pdf`
    : `/official-forms/${internshipId}/journal.pdf`
  const res = await api.get(endpoint, { params: extraParams, responseType: 'blob' })
  const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

export async function openOfficialFo30(internshipId, setPreviewModal, extras = {}) {
  if (!internshipId) return
  const bundle = await fetchOfficialForm(internshipId)
  const studentName = bundle.fo30?.student_name || 'Student'
  setPreviewModal({
    type: 'dtr',
    data: fo30PreviewData(bundle),
    onDownload: extras.onDownload === false
      ? undefined
      : () => downloadOfficialPdf('dtr', internshipId, `DTR_${studentName}.pdf`, extras.pdfParams || {}),
  })
}

export async function openOfficialFo31(internshipId, journal, setPreviewModal, extras = {}) {
  if (!internshipId) return
  const bundle = await fetchOfficialForm(internshipId)
  const data = fo31PreviewData(bundle, journal)
  setPreviewModal({
    type: 'journal',
    data,
    onDownload: extras.onDownload === false
      ? undefined
      : () => downloadOfficialPdf('journal', internshipId, `Journal_${data.studentName}.pdf`, extras.pdfParams || {}),
  })
}
