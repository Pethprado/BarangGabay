<?php
/**
 * The retry forms for _language-status-card.php. Include ONCE, at the very
 * end of the page, outside every other form.
 *
 * Why they are not written where the buttons are: the status card sits in
 * the middle of the post's own edit form, and a <form> written there is
 * nested inside it. Nested forms are invalid HTML — the browser drops the
 * inner one and its submit button posts the OUTER form, so "Translate now"
 * would save the post instead of translating a language. It looks like it
 * works, which is the worst kind of broken.
 *
 * A button may reference a form anywhere in the document by id, so the
 * card records what it needs and this renders it somewhere legal.
 *
 * Renders nothing when no card asked for anything.
 */

$lrfForms = $GLOBALS['bg_retry_forms'] ?? [];

foreach ($lrfForms as $lrf): ?>
<form method="post"
      action="<?= e(route('admin/retranslate')) ?>"
      id="<?= e($lrf['id']) ?>"
      class="d-none">
    <input type="hidden" name="csrf_token"   value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="content_type" value="<?= e($lrf['type']) ?>">
    <input type="hidden" name="content_id"   value="<?= (int) $lrf['contentId'] ?>">
    <input type="hidden" name="lang"         value="<?= e($lrf['lang']) ?>">
    <input type="hidden" name="redirect"     value="<?= e($lrf['redirect']) ?>">
</form>
<?php endforeach;

// Cleared so a second include cannot emit the same ids twice — duplicate
// ids would make form="…" resolve to whichever came first.
$GLOBALS['bg_retry_forms'] = [];
