# Decision register

## 2026-10-12 — Independent multiple-post matching application

Keep only login, user management, file upload, matching and exports. Use a separate project and database from the Choice Taking System.

## 2026-10-12 — Dynamic field preservation

Store every named uploaded column, preserving displayed string values. Prepare a separate normalized identity for matching. USER/REG are not cross-post identity keys. Matching headers are fixed: name, fname, mname, b_date, s_roll, s_year, h_roll, h_year, nid_no. fname, mname, h_roll, h_year and nid_no are optional. Name comparisons allow full/partial matches; DOB, SSC identifiers, HSC identifiers and NID comparisons use full equality.

Current automatic rule: exact name + DOB + SSC roll/year, with no comparable optional-field conflict. Possible name matches require full DOB and SSC equality and explicit review. Detailed behavior and DOB normalization are documented in matching-and-import.md.

## 2026-10-12 — Export preservation

Individual exports keep all original post fields plus reg_POSTCODE/user_POSTCODE columns. Combined exports provide one row per person, a union of original headers and post-specific field copies so different source values remain available. Detailed collision handling is documented in export-format.md.

## 2026-10-12 — Header/footer and documentation policy

Use the style and format of the latest supplied Exam Scheduler reference (`project-reference-09-10-2026-11-38-PM.zip`), adapted to this project's name and existing menu options. Use a matching icon; retain the Scheduler footer's organization, developer credit, software version and developer line. Do not add Scheduler modules or a language switch.

The root README is limited to the project name, short overview, installation and development command list. It is not updated through Paste & Replace. Ongoing documentation and development decisions live in docs/ and may be updated with patches.

## 2026-10-12 — Review guidance

Add a final Remarks column with background-colored Exact match, Partial match, Not matched and Not compared badges. Move Confirm Match / Reject Match radio decisions below the table, followed by Save Decision. USER/REG and blank optional values are Not compared. Multiple proposed people remain distinguishable.

Confirm Match uses a green radio-button control; Reject Match uses red. Selecting an option fills its background. Radio semantics remain mutually exclusive.

## 2026-10-12 — Workspace layout and export ordering

Place Add a post at the end with a button-revealed form. Present individual exports for all uploaded posts and All Combine / Only Unique in tabular form, following the original Multiple Post Choice Taking layout. Combined order is added post serial, then reg; Only Unique retains each person's earliest sorted occurrence. Replace decision-style buttons with visible radio options in separate green Confirm and red Reject groups.

## 2026-10-12 — Edge-to-edge shell and DBF exports

Reset HTML/body/sidebar offsets and make header/footer inner containers full width with modest side padding. Reduce the left product-name size. Add plain DBF to all candidate export modes. Preserve source strings, zero-prefixed identifiers and UTF-8 bytes; map names and split long text within DBF limits. Include the DBF field map in XLSX rather than bundling DBF in a ZIP.

## 2026-10-12 — Add a post placement correction

Page order: existing post cards → collapsible Add a post → Candidate Exports. This supersedes the earlier placement after exports.

## 2026-10-12 — Export header fidelity

Keep uploaded titles exactly in XLSX. Preserve DBF-compatible titles and their case; use short names only where DBF limits require them. User explicitly selected short DBF titles instead of blocking solely on unsupported titles. Generated-column collisions must not rename uploaded titles.

The user subsequently requested no separate field-mapping deliverable. v1.0.6 removes the XLSX DBF Field Map worksheet; direct single-file DBF downloads remain unchanged. Original XLSX titles remain exact; DBF-compatible titles preserve case and incompatible titles receive deterministic short names.

## 2026-10-12 — Export table alignment

Post Title and Individual Export retain left alignment. Other export-table values are centered horizontally and vertically. Table headings and section titles are bold.

## 2026-10-12 — Header/footer content width correction

Only backgrounds span the viewport. Masthead, navigation and footer content use the same container-xl width, responsive gutters and centered alignment as the body. This supersedes the full-width inner-container decision in v1.0.4.

## 2026-10-12 — Combined/unique schema simplification

Only shared uploaded headers plus reg_POSTCODE, user_POSTCODE, matching_post and matching_status in combined/unique exports. Remove all other generated per-post fields and group/count metadata from those modes. Individual source data and internal storage remain complete. This supersedes the original union/post-specific preservation export design.

## 2026-10-12 — Matching post metadata in every mode

Add matched_post_count to Combined/Unique. Add matching_post to Individual alongside its existing matched_post_count. Both list/count include the source post and all linked posts, in added-post order. This revises v1.0.8 only by adding the requested count.
