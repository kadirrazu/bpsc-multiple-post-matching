# Upload and matching rules

## Workflow

1. Create a matching workspace, normally one advertisement/recruitment exercise. Its posts are matched only against each other.
2. Add posts with unique alphanumeric post codes (up to 20 characters). Leading zeros in codes are retained.
3. Download the sample, or upload your own XLS/XLSX file for each post. The first worksheet is used, with column headers on row 1. Limits: 20 MB, 10,000 candidate rows, 200 columns.
4. Every named column and its original displayed string value is stored, including unknown/dynamic columns. Blank values, Bangla text, original DOB text and leading zeros in text/formatted cells are retained. Use text cells for identifiers; leading zeros already lost in the source workbook cannot be recovered. Headers must be nonblank and unique, ignoring case. Header titles are preserved as supplied. Formulas are rejected: paste values first. A workbook with any invalid record is rejected atomically.
5. Exact matches link automatically. Review every possible match; link the correct person or keep the row separate. Pending rows are never silently merged, and exports wait until all reviews in the workspace are complete.
6. Export individual post files or one combined unique file. Both offer an optional matched-only export (a person appearing in at least two posts). Dataset deletion requires typing DELETE and allows reimport.

## Fixed identity fields

Required columns and nonblank values: `user`, `reg`, `name`, `b_date`, `s_roll`, `s_year`.

Optional matching columns: `fname`, `mname`, `h_roll`, `h_year`, `nid_no`. They may be absent or blank. Every other named column is dynamic and retained. Matching headers are case sensitive. USER/REG are identifiers, never cross-post matching keys.

Matching decisions:

- Exact `name + b_date + s_roll + s_year` links automatically when comparable optional fields do not conflict. Exact names use trimmed values, without fuzzy normalization.
- Possible matches require full DOB and SSC roll/year equality, plus a full/partial candidate-name match or both parent-name similarities. Name comparison handles common prefixes and small spelling differences; a common surname alone is insufficient.
- Optional HSC roll/year and NID are compared by exact value when supplied on both sides. Conflicting optional identifiers, or dissimilar nonblank parent names on an otherwise exact match, send the row to review. They do not silently override conflicting mandatory DOB/SSC fields.
- Review links are explicit operator decisions, recording who reviewed and when. A person may have only one record per post. Duplicate exact identities, USER IDs or registrations within one uploaded post are rejected. Multiple possible exact groups require review.

DOB matching supports DDMMYYYY, DDMMYY, ISO YYYY-MM-DD, and actual Excel date cells. Two-digit year pivot: 00–30 → 2000–2030, 31–99 → 1931–1999. Thus 101000 → 2000-10-10, 101097 → 1997-10-10, 101005 → 2005-10-10. Normalization affects the matching identity only; the uploaded field remains unchanged. SSC/HSC years, when present, use four digits.

## Review remarks and decisions

The final Remarks column shows each field against each proposed person: green Exact match, blue Partial match, red Not matched, gray Not compared. Partial matching applies only to name/fname/mname; other identity fields require full equality. DOB uses its normalized identity value. Blank values and USER/REG are Not compared. With multiple suggestions, each remark identifies its post and group.

Choose Confirm Match for the intended person or Reject Match to keep a separate person, then Save Decision. No option is preselected. Multiple people have distinct Confirm Match radio options in the same decision group. Existing server-side eligibility and stale-review checks remain authoritative.

Confirm Match uses a green radio-button control; Reject Match uses red. Selecting an option fills its background. Radio semantics remain mutually exclusive.

In v1.0.3 the decision options are ordinary visible radio controls inside separate green Confirm Match and red Reject Match groups; they do not resemble submit buttons. All options retain one mutually exclusive decision field. Add a post is positioned after existing posts and before Candidate Exports; a collapse button reveals the form. Validation failures for the post fields reopen the form.
