# Export format

## Individual

Individual exports retain every original column of the selected post, with exact uploaded header titles in XLSX. They also retain the existing matching USER/REG columns, group ID, matched-post count, matching_post codes and status. Rows sort by reg ascending.

## All Combine / Only Unique

The original fields are the exact header intersection of all uploaded post files, in the earliest added uploaded post's header order. Unuploaded/empty posts do not participate. Only these additional matching fields are generated:

- reg_POSTCODE and user_POSTCODE for each uploaded post
- matching_post
- matched_post_count
- matching_status

No other post-suffixed fields such as batil_POSTCODE, nid_no_POSTCODE or district_POSTCODE, group IDs are included in combined modes. Uploaded original fields such as batil/nid_no remain if common to all uploaded files.

matched_post_count is the number of distinct post codes in matching_post, including the source post (a person in one post has count 1). Both code list and count appear in Individual, All Combine and Only Unique.

matching_post lists the linked person's post codes, including the source post, in added-post order, separated by |. matching_status retains the existing NEW/EXACT/CONFIRMED status semantics. Pending reviews block exports.

All Combine retains every application row and takes common-field values directly from that original row. Only Unique keeps each confirmed person once, taking the first row in added-post-serial ASC / reg ASC order without filling blank fields from other records. Internal source data remains complete; use Individual exports for post-specific original fields.

## XLSX and plain DBF

Both formats use the same selected rows and schema. XLSX has one candidate worksheet and explicit text cells to preserve leading zeros. Original titles take priority; a generated column gets a __2/__3 suffix if its name collides with an original title.

DBF downloads directly as one .dbf file, without ZIP, memo or mapping files/sheets. It preserves valid unique ASCII titles up to 10 characters and their case. Longer titles are shortened; unsupported characters become underscores. Long user_POSTCODE titles use USR_POSTCODE where possible. Conflicts receive deterministic short aliases.

Character fields retain UTF-8 bytes. Text exceeding 254 bytes is split into numbered fields without splitting multibyte characters or truncating data. Use a UTF-8-aware reader for Bangla; ANSI-only legacy readers cannot display Unicode correctly. Physical limits are 255 DBF fields and 65,535 bytes per record; exceeding them produces an explicit validation error rather than dropping data.

New uploads retain dynamic header text verbatim, including surrounding spaces; matching headers still use the fixed exact field names. Whitespace-only headers are invalid.
