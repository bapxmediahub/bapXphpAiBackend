---
type: doc
title: Historical Appointment Records
description: Owner-only access to appointment records retained after public consultation booking was retired.
category: module
---
# Historical Appointment Records

Public consultant booking was retired in September 2026. `ConsultationController.php` now redirects legacy submissions to the general enquiry form; historical appointments remain accessible to the owner through `AdminController.php` and `/admin/appointments`.

Key checks: public consultation URLs redirect to the shop, legacy submission URLs redirect to Contact, and only an administrator can update a retained appointment record.
