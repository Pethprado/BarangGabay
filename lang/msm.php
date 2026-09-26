<?php
declare(strict_types=1);

/**
 * Manobo (ISO 639-3: msm) UI strings.
 *
 * Intentionally empty — and it should stay that way.
 *
 * Unlike en.php and fil.php, this locale is not hand-authored. Nobody on this
 * project speaks this language, so inventing UI labels here would put made-up
 * words in front of the very community the app serves. Instead, t() resolves
 * every label through the curated dataset in
 * data/manobo/manobo_dictionary.csv (see app/helpers.php::manobo_word).
 *
 * The practical effect:
 *   - A label whose English text matches a dataset entry renders in Manobo.
 *   - A dataset entry may itself be a Surigaonon or Bisaya word — that is
 *     correct, not a compromise. Residents here naturally code-switch
 *     Manobo with Surigaonon/Bisaya, so a dictionary entry that reflects
 *     that is more faithful than an invented "pure" Manobo word would be.
 *     See the hint text on /admin/manobo's add-word form.
 *   - Everything the Manobo dataset does not cover is looked up next in the
 *     separate Bisaya dictionary (data/bisaya/bisaya_dictionary.csv), then
 *     blended word-by-word (Manobo word first, else Bisaya), and only then
 *     falls back to Filipino, then English (see app/helpers.php::t()).
 *     Bisaya/Surigaonon reads far more naturally to a Manobo speaker here
 *     than English does.
 *   - Adding words at /admin/manobo immediately widens Manobo coverage with
 *     no code change and no edit to this file.
 *
 * Add a key below ONLY for a phrase a Manobo speaker has confirmed that does
 * not fit the dataset's word-level shape (a whole sentence, say). Anything
 * that is a single word or a short phrase belongs in the CSV, where it is
 * sourced, reviewable and reusable by the translator module.
 *
 * This is now the only Manobo in the system. There used to be a lang/mbb.php
 * for Western Bukidnon Manobo — a different language from a different
 * province, which nobody in Bayogo speaks. It held no strings and has been
 * removed; msm is the language the dictionary, the AI prompt and the locale
 * switch all target. See data/manobo/README.md.
 */
return [];
