# IT expert instrument — INTERNTRACK (ISO/IEC 25010)

**Instructions:** Review the system (demo + codebase overview as provided). Rate each statement **1 (Strongly Disagree)** to **5 (Strongly Agree)**.

**Expert background:** _________________  **Date:** ____________

---

## Functional suitability

| ID | Statement | 1 | 2 | 3 | 4 | 5 |
|----|-----------|---|---|---|---|---|
| IT-FS1 | Role-based workflows cover the stated internship management objectives. | | | | | |
| IT-FS2 | Document routing (coordinator → faculty) is coherent and complete for the MVP. | | | | | |
| IT-FS3 | Scope boundaries (e.g. mock MISD vs live SSO) are clearly reflected in the implementation. | | | | | |

## Performance efficiency

| ID | Statement | 1 | 2 | 3 | 4 | 5 |
|----|-----------|---|---|---|---|---|
| IT-PE1 | API design is appropriate for the demonstrated data volumes. | | | | | |
| IT-PE2 | Critical list endpoints avoid obvious N+1 / full-table hydrate issues in reviewed code. | | | | | |
| IT-PE3 | Optional realtime (Reverb) with poll fallback is a reasonable efficiency trade-off. | | | | | |

## Compatibility

| ID | Statement | 1 | 2 | 3 | 4 | 5 |
|----|-----------|---|---|---|---|---|
| IT-CO1 | Stack choices (React + Laravel Sanctum + MySQL) are compatible with typical campus deployment. | | | | | |
| IT-CO2 | Integration approach (mock MISD / Admin Sync) is a compatible interim strategy pending institutional API. | | | | | |

## Usability (expert)

| ID | Statement | 1 | 2 | 3 | 4 | 5 |
|----|-----------|---|---|---|---|---|
| IT-US1 | Role portals present a consistent interaction pattern. | | | | | |
| IT-US2 | Status labeling and progress indicators support operator understanding. | | | | | |

## Reliability

| ID | Statement | 1 | 2 | 3 | 4 | 5 |
|----|-----------|---|---|---|---|---|
| IT-RE1 | Validation and error responses are structured enough for client handling. | | | | | |
| IT-RE2 | Feature tests / seed demos support regression confidence for core flows. | | | | | |
| IT-RE3 | Preference-gated notifications reduce noisy delivery without breaking audit needs. | | | | | |

## Security

| ID | Statement | 1 | 2 | 3 | 4 | 5 |
|----|-----------|---|---|---|---|---|
| IT-SE1 | Sanctum auth + role middleware provide a sound baseline for the demo. | | | | | |
| IT-SE2 | Internship messaging / meetings enforce participant boundaries appropriately. | | | | | |
| IT-SE3 | Electronic signatures are correctly framed as acknowledgments (not PKI), reducing false security claims. | | | | | |
| IT-SE4 | Remaining gaps (Policies depth, MFA) are acknowledged and acceptable for capstone MVP scope. | | | | | |

## Maintainability

| ID | Statement | 1 | 2 | 3 | 4 | 5 |
|----|-----------|---|---|---|---|---|
| IT-MA1 | Controllers/services are structured enough for student-team maintenance. | | | | | |
| IT-MA2 | Shared utilities (required documents, signature capture, notification prefs) reduce duplication. | | | | | |
| IT-MA3 | README / progress notes make operations (migrate, seed, Reverb) maintainable for demos. | | | | | |

## Portability

| ID | Statement | 1 | 2 | 3 | 4 | 5 |
|----|-----------|---|---|---|---|---|
| IT-PO1 | The system can be stood up on a typical local stack (Laragon/XAMPP + Node) from documentation. | | | | | |
| IT-PO2 | Environment-based configuration (API URL, Reverb keys) supports moving between machines. | | | | | |

## Expert open comments

1. Top technical risk before production:  
2. Highest-value next refactor:  
3. Is mock MISD framing acceptable for defense?  
