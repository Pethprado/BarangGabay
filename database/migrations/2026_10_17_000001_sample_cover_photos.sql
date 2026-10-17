-- Sample post covers are now real, openly licensed Wikimedia Commons photos
-- (public/assets/images/posts/*.jpg, credited from credits.json). The drawn
-- .png covers they replace were removed, so point the sample rows at the .jpg
-- of the same name.
--
-- Replays on every connect, but it only matches is_sample rows still on an
-- old bundled .png, so after the first run it changes nothing — and it never
-- touches a cover a staff member uploaded (those live under uploads/).

UPDATE announcements SET cover_image_url = REPLACE(cover_image_url, '.png', '.jpg')
 WHERE is_sample = 1 AND cover_image_url LIKE 'assets/images/posts/%.png';

UPDATE events SET cover_image_url = REPLACE(cover_image_url, '.png', '.jpg')
 WHERE is_sample = 1 AND cover_image_url LIKE 'assets/images/posts/%.png';
