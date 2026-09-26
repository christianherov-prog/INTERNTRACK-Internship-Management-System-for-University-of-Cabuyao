// FO-24 signer on the frontend: the official form prints the submitting
// supervisor's signature resolved by the server, never the internship's
// current supervisor (FO24-SIG-03 on the client side).
import { test, before } from 'node:test'
import assert from 'node:assert/strict'
import { importBundle } from './helpers/bundle.mjs'

let evaluationSignaturePath
before(async () => {
  ({ evaluationSignaturePath } = await importBundle(
    "export { evaluationSignaturePath } from './src/utils/formIdentity.js'"
  ))
})

test('the server-resolved signature is printed', () => {
  assert.equal(
    evaluationSignaturePath({ id: 1, signature_path: null, resolved_signature_path: 'signatures/97_processed.png' }),
    'signatures/97_processed.png'
  )
})

test('a server-rejected stored signature never comes back through the raw column', () => {
  // The server resolved "no usable signature" for this signer: print a blank line.
  assert.equal(
    evaluationSignaturePath({ id: 1, signature_path: 'signatures/39_processed.png', resolved_signature_path: null }),
    ''
  )
})

test('older payloads without the resolved field fall back to the stored signature only', () => {
  assert.equal(evaluationSignaturePath({ id: 1, signature_path: 'signatures/evaluations/8/a.png' }), 'signatures/evaluations/8/a.png')
  assert.equal(evaluationSignaturePath({ id: 1 }), '')
})

test('a blank form (no submitted evaluation) has no signature', () => {
  assert.equal(evaluationSignaturePath(null), '')
  assert.equal(evaluationSignaturePath({ resolved_signature_path: 'signatures/1_processed.png' }), '')
})
