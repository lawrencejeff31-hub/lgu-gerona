# Workflow Approval API Task

This task adds explicit document approval (without digital signature) to the workflow service and API. It complements the existing PNPKI `sign` endpoint and provides a clear, policy-driven path to set documents as `approved`.

## Specifications

- Approve
  - Inputs
    - `documentId` (path): numeric ID of the document.
    - `remarks` (body, optional string, max 1000): freeform notes for approval.
  - Outputs
    - `200 OK` with `data` containing the updated document, including `status`, `approved_by`, `approved_at`.
    - `422 Unprocessable Entity` when approval is invalid for the current status.
    - `403 Forbidden` when the user is not authorized to approve.
    - `500 Server Error` for unexpected failures.
  - Preconditions
    - Document must be in an approvable state: `received`, `under_review`, or `for_approval`.
    - User must be authorized per `DocumentPolicy::approve` (admin or user in same department or creator).

- Disapprove (Reject)
  - Inputs
    - `documentId` (path): numeric ID of the document.
    - `reason` (body, required string, max 1000): rejection reason.
  - Outputs
    - `200 OK` with `data` containing the updated document, including `status`, `rejected_by`, `rejected_at`.
    - `403 Forbidden` when the user is not authorized to reject.
    - `500 Server Error` for unexpected failures.
  - Preconditions
    - Document can be rejected during review stages (e.g., `submitted`, `received`, `under_review`, depending on business rules already implemented).
    - User must be authorized per `DocumentPolicy::update` (existing reject endpoint uses `update`).
- Performance
  - Single transaction-free update; completes in O(1) operations.
  - Expected latency under 150–200ms on typical dev hardware; no N+1 queries.
- Constraints
  - Approval is idempotent only while status is already `approved` (caller should avoid redundant calls).
  - Approval does not attach a digital signature; use `/sign` for PNPKI flows.
  - Disapprove maps to existing `reject` endpoint and follows current service rules.
- Error Handling
  - Invalid state: 422 with `errors.status` explaining reason.
  - Authorization failure: 403 per policy.
  - Generic exceptions are logged and returned as 500.

## Implementation

- Model helper: `Document::canBeApproved()` allows `received`, `under_review`, `for_approval`.
- Service: `DocumentWorkflowService::approveDocument()` updates status, sets `approved_by`/`approved_at`, and logs `approved` action.
- Controller: `WorkflowController::approve()` validates input, authorizes with `approve`, calls service, and maps errors to HTTP codes.
- Disapprove: Use existing `WorkflowController::reject()` (`POST /api/workflow/documents/{document}/reject`).
- Routes: `POST /api/workflow/documents/{document}/approve`.
- Status API: includes `can_approve` flag.
- Frontend store: `approveDocument(documentId, remarks)`.

## Usage Examples

- Curl
  - `curl -X POST -H "Authorization: Bearer <token>" -H "Content-Type: application/json" -d '{"remarks":"Reviewed"}' http://localhost:8000/api/workflow/documents/123/approve`
- Axios
  - `await workflowStore.approveDocument(123, 'Reviewed');`

## Unit & Feature Tests

- `test_can_approve_document`: `received` → `approved`, sets `approved_by` and `approved_at`.
- `test_cannot_approve_invalid_status`: `submitted` → 422, no status change.
- `test_approve_requires_authorization`: different department → 403.

## Testing Methodology

- Run focused tests: `php artisan test --filter approve`.
- Verify status endpoint reflects `can_approve` when conditions are met.
- Test with different statuses: `received`, `under_review`, `for_approval`, `submitted`.

## Performance Benchmarking

- Measure request time locally using browser devtools or `time curl ...`.
- Confirm minimal DB operations via Laravel Telescope or query logging.

## Code Review Checklist

- Authorization uses `DocumentPolicy::approve`.
- Input validation limits remarks length.
- Service updates only relevant fields atomically.
- Logs include useful metadata and current status.
- Status API reflects new capability.
- No duplicate logic with PNPKI `sign` path; behavior documented.

## User Acceptance Criteria

- Authorized users can approve `received` documents successfully.
- UI can conditionally enable approve when `can_approve` is true.
- Audit logs show `approved` action with remarks.

## Deployment Instructions

- No migrations required; ensure `approved_by` and `approved_at` columns exist.
- Clear caches if necessary: `php artisan optimize:clear`.
- Confirm route availability: `php artisan route:list | findstr approve` (Windows).