## Prepare an unpublished article

In **Blog**, choose **New Post**, enter a title and write the article. Under **Post settings**, leave the publication setting on **Draft — not public**. Check **Preview**, then choose **Save** to keep the draft in the editor. You can reopen it from the blog list.

## Create or edit the article

Open Blog and choose **New Post** or an existing record. Set a clear title and suitable category. Expand **Post settings** for the slug, template, image, excerpt, summary and search metadata. For a customer guide, explain an actual task using the current interface rather than inventing functionality.

## Draft, enhance and preview

Use the document/Markdown modes to edit and Preview to inspect the result. Template scaffolding and AI Enhance controls return content for review; they are not an automatic publication. Choose **Enhance title** or **Enhance content**, review the AI suggestion, then use **Apply to title** or **Apply to content**. Review names, links, instructions and policy claims. If the provider is unavailable, keep editing or review the separately labelled local formatting option when offered. You can dismiss either suggestion. Images must come from a valid uploaded or bundled asset with a useful description.

## Save deliberately

Review the publication setting and use the explicit save action. Open the public article after saving to check formatting, image and links. Future dates now control public visibility at midnight Asia/Kolkata; they do not schedule newsletters. A saved unpublished post should not be treated as a public guide.

Do not add customer records, API keys or internal incident notes to a public article. Technical defects and task status belong in the team's issue tracker, not customer help text.

## Schedule safely and avoid overwriting another editor

New articles start as **Draft — not public**. Choose **Publish when the date is
due** when the article is approved. A date in the future is shown as Scheduled;
it is withheld from the article URL, listings, search, sitemap and agent article
retrieval until midnight Asia/Kolkata. A hidden or module-disabled article stays
unavailable. Scheduled visibility does not automatically send email.

Each edit remembers the version you opened. If another editor saves first,
your save, visibility toggle or delete is rejected instead of overwriting the
newer file. Your rejected text remains in the editor. Use **Download unsaved
Markdown**, then **Reload current article**, compare the changes and save a
reviewed version. There is no force-overwrite control. Changing an existing
title keeps its original URL; URL renaming is not part of this operation.

## Restore an earlier server save

Choose **History** beside the article, then select a UTC timestamp to inspect its saved Markdown and publication settings. **Restore this version** replaces the current article with the selected version. Check the resulting publication state before restoring. A restore can make an article public immediately.

The editor keeps the ten most recent changes. Restoring also saves the version you replaced. If another editor saves after you open the preview, reopen the desired version and review it again. Deleting the article also deletes its revision history.


## Publish a standalone page

Open **Pages** in the sidebar. Give the page a title and a short address, then
write its Markdown. In **Header image**, choose an uploaded image or use
**Upload to Media Library** to add one. Describe informative images in
**Alternative text**. For a text-only page, choose **No header image**.
Under **Enquiry form**, select **Show an enquiry form** to collect a name and
email. To ask for more information, choose a type and enter a label for up to
three fields. Tick **Required** on any field the visitor must complete.
Choose **Unused** to remove a field from the form. Clear **Show an enquiry
form** to remove the whole form when you save.

In **Downloads**, select **Enable downloads** and use **Add files** for files
that together total 5 MB or less. Choose a different **Position** for each
existing file, or choose **Remove**. New files go after the retained files.
Preview lists the files without active download links. Re-select any new
files after preview. Clear **Enable downloads** and save to hide downloads
while retaining the current selection. Published pages with an enquiry form
require a confirmed submission before visitors can download.

Use **Preview** to inspect the unsaved title, image, Markdown and enquiry
fields. The preview form is disabled. Select **Published** and **Save page**
when it is ready. Use **Open published page**
to check it. Select **Draft** and save to withdraw the page from its address,
search, and the sitemap. The address stays fixed across edits. If another
editor saved first, download the browser draft before reopening the current version.
Unsaved page title, address, Markdown, header choice, enquiry settings and saved download positions are saved in this browser when storage is available. Reopen the same page as the same owner to restore or discard the draft. If the saved page changed, download the browser copy instead; review visibility before using Save page. Autosave does not publish or send content to the server.
Review submitted enquiries in **Contact Submissions** or **Email → Inbox**.
Inbox shows the campaign address and revision alongside each enquiry. The
visitor sees a receipt on the page.

To remove the page and its text permanently, open **Delete page**, tick the
confirmation and choose **Delete this page**.

In **Pages**, turn on **Enable payment** and enter the booking amount in INR.
The preview shows the amount. Save the page to make it available to visitors.
Booking works with or without enquiries. If downloads are also enabled,
visitors retrieve the purchased file versions from their confirmed booking.

Set up Razorpay API credentials and the webhook secret for the selected payment
mode in **Integrations**. Walk through a booking in test mode before accepting
payments. The booking reference appears on the visitor's status page when
Razorpay confirms capture.
