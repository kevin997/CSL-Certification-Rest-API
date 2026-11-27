# Academy workflows V1

Implement a typed workflow API per academy, using the approved KURSA audit. Preserve all legacy templates. Workflows have one trigger, an optional tenant-owned resource filter, and up to one action per channel. Separate the editable draft from its active version. Pause prevents new actions; republishing starts a new revision without replaying old events. Simulation uses made-up data only. Expose a concise run journal without message bodies or contact details.

- Persist independent workflow definitions and unique delivery reservations. Test reads/writes, licence checks, filters, drafts, published revisions and cross-tenant denial.
- Listen after transactions commit. Reuse real sales-form, order, and certification signals. Only match payment when a positive, in-tenant commerce settlement is verified. No pending-payment trigger until unified deduplication with the legacy scheduler is implemented.
- Reload scope, recipient, consent, workflow state and channel availability at execution. Use existing real channels; only approved in-academy recipient relationships. Prevent duplicated actions across custom workflows and conservatively block potentially overlapping system workflows. Never retry ambiguous external dispatch.
- In frontend add “My workflows” alongside existing system templates, a guided node canvas and accessible side panels. Test actual draft, simulation, explicit publication, pause and history contracts. No workflow should be created or activated in production by the deployment itself.
