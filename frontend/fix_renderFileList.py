import re

with open("src/pages/student/portfolio/COEDPortfolioBuilder.jsx", "r", encoding="utf-8") as f:
    content = f.read()

fancy_renderFileList = """  const renderFileList = (type, title, requiresWeek = false, requiresLabel = false, accept = "image/*,.png,.jpg,.jpeg,.webp,.gif,.pdf", tip = "") => {
    const items = getFiles(type);

    return (
      <div className="content-card portfolio-upload-tile">
        <div className="portfolio-upload-tile-head">
          <h6 className="mb-0">{title}</h6>
          {tip && <span className="portfolio-upload-tip" title={tip}><i className="fa fa-circle-info"></i></span>}
        </div>
        <div className="portfolio-upload-tile-body">
          <div className="portfolio-upload-tile-content">
            {items.length > 0 ? (
              <div className="portfolio-upload-files">
                {items.map(item => (
                  <div key={item.id} className="portfolio-upload-file-row">
                    <div className="text-truncate flex-grow-1 me-2 small">
                      {item.file_path && item.file_path.endsWith('.pdf')
                        ? <i className="fa fa-file-pdf text-danger me-1"></i>
                        : <i className="fa fa-image text-primary me-1"></i>}
                      {item.label || item.file_name}
                    </div>
                    <div className="d-flex gap-1 flex-shrink-0">
                      <AuthenticatedFileLink path={item.file_path} className="btn btn-outline-secondary btn-sm" style={{ padding: '0.1rem 0.35rem' }}>
                        <i className="fa fa-eye"></i>
                      </AuthenticatedFileLink>
                      <button type="button" className="btn btn-outline-danger btn-sm" style={{ padding: '0.1rem 0.35rem' }} onClick={() => deletePhoto(item.id)}>
                        <i className="fa fa-trash"></i>
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="portfolio-upload-empty">No files yet</p>
            )}
            {tip && <p className="portfolio-upload-hint">{tip}</p>}
          </div>
          <div className="portfolio-upload-btn-wrap">
            <input type="file" id={`upload-${type}`} className="d-none" accept={accept} onChange={(e) => handleFileUpload(e, type, true)} />
            <label htmlFor={`upload-${type}`} className="btn btn-outline-primary btn-sm w-100 portfolio-upload-btn mb-0">
              <i className="fa fa-cloud-arrow-up me-1"></i> Upload File
            </label>
          </div>
        </div>
      </div>
    );
  }"""

# Find the old renderFileList and replace it
# The old renderFileList starts with `const renderFileList = (type, title) => {` and ends before `const renderTextArea = ` or `const textFields = `
pattern = r'const renderFileList = \(type, title\) => \{.*?(?=const renderTextArea = |const textFields = )'
content = re.sub(pattern, fancy_renderFileList + '\n\n  ', content, flags=re.DOTALL)

with open("src/pages/student/portfolio/COEDPortfolioBuilder.jsx", "w", encoding="utf-8") as f:
    f.write(content)
