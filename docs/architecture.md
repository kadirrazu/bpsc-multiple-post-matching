# Architecture and scope

The project is derived from the 12 October 2026 BPSC Choice Taking System reference. It is an independent Laravel/MySQL application with the existing staff authentication and user-management foundation.

The functional scope is staff login, user management, post-wise file upload, identity matching/review and exports. Public candidate login, choices, submissions, receipts, event publishing and archive workflows are removed.

A matching workspace groups the posts of one recruitment exercise; matching never crosses workspace boundaries. `matching_posts` retains the uploaded header list and filename. `matching_records.data` stores every original named field as an original displayed string, while `matching_records.identity` stores the separate normalized matching inputs. `matching_people` groups confirmed records across posts.

Administrators manage users. Active Operators can upload, review and export; both roles use the same matching workspace interface. Review records retain the reviewer and review timestamp. Only one confirmed record for a person is allowed within a post.

Imports, decisions, dataset resets and export snapshots acquire a workspace lock to avoid inconsistent concurrent changes. A workbook with invalid rows is rejected atomically. Dataset replacement requires deleting the existing post dataset by typing DELETE, followed by reimport.

The interface uses the Exam Scheduler shell: a pale green full-width masthead with software name left and commission name right, a separate white navigation row, and a full-width footer with two lines. The English interface and matching-specific navigation are retained. The version comes from `config/matching.php` and is shared by the signed-in and login-page footer.
