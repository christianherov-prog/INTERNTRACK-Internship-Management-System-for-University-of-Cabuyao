import { AuthenticatedFileImage } from '../AuthenticatedFile'

/**
 * Official portfolio signature stack: image centered above printed name.
 * The PNG itself must be transparent; CSS only keeps the box clear.
 */
export default function PortfolioSignature({
  path,
  printedName,
  maxHeight = 42,
  maxWidth = 180,
}) {
  const srcPath = typeof path === 'string' ? path.trim() : path
  return (
    <div className="portfolio-signature-stack">
      <div className="portfolio-signature-image-wrap">
        {srcPath ? (
          <AuthenticatedFileImage
            path={srcPath}
            alt=""
            className="portfolio-signature-img"
            style={{
              maxHeight: `${maxHeight}px`,
              maxWidth: `${maxWidth}px`,
              height: 'auto',
              width: 'auto',
              objectFit: 'contain',
              display: 'block',
              background: 'transparent',
            }}
          />
        ) : null}
      </div>
      {printedName ? (
        <div className="portfolio-signature-name">{printedName}</div>
      ) : null}
    </div>
  )
}
