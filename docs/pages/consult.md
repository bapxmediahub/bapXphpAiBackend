---
type: doc
title: Consult Page
description: Route /consult - show remote consultant profiles with search, language filtering, and one scheduled-booking path.
category: page
---
# Consult Page

Route: `/consult`

Controller: `PublicController@consult`

Purpose: show remote consultant profiles with search, language filtering, and one scheduled-booking path.

Key checks: cards link to consultant profiles; profile forms post date, time, phone, notes, and CSRF to `/consultation/initiate`; guest submissions redirect to login.
Cards use stable portrait and action geometry. Languages, experience, speciality, and verified reviews come from remote profile data; missing values are omitted rather than fabricated.
