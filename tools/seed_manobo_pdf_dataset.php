<?php
declare(strict_types=1);

/**
 * Seeds and updates manobo_dictionary with the full 234 vocabulary entries
 * extracted from Manobo Words.pdf, enriched with Bisaya equivalents, page numbers,
 * types (phrase/word), priorities, normalized keys, and review statuses.
 */

if (!function_exists('seed_manobo_normaliseText')) {
    function seed_manobo_normaliseText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/\s*\([^)]*\)/u', '', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B.,;:!?\"'“”()[]");
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }
}

function seed_manobo_pdf_dataset(?PDO $pdo = null): array
{
    if ($pdo === null) {
        $pdo = db();
    }

    $dataset = [
    [1, 'madjow no masim', 'Magandang umaga', 'Good morning', 'Maayong buntag', 'phrase', 'phrase', 10, 1, 'approved', 0, ['good morning', 'magandang umaga', 'maayong buntag'], null],
    [2, 'madjow no madukilom', 'Magandang gabi', 'Good evening', 'Maayong gabii', 'phrase', 'phrase', 10, 1, 'approved', 0, ['good evening', 'magandang gabi', 'maayong gabii'], null],
    [3, 'naliyagan ko sikuna', 'Gusto kita', 'I like you', 'Ganahan ko nimo', 'phrase', 'phrase', 10, 1, 'approved', 0, ['i like you', 'gusto kita', 'ganahan ko nimo'], null],
    [4, 'hunda kumosta', 'Kumusta ka?', 'Hi, how are you?', 'Kumusta ka?', 'phrase', 'phrase', 10, 1, 'approved', 0, ['hi how are you', 'kumusta ka', 'kamusta'], null],
    [5, 'nahigugma a ikow', 'Mahal kita', 'I love you', 'Gihigugma ko ikaw', 'phrase', 'phrase', 10, 1, 'approved', 0, ['i love you', 'mahal kita', 'gihigugma tika'], null],
    [6, 'ande ka tug deg', 'Saan ka pupunta?', 'Where are you going?', 'Asa ka paingon?', 'phrase', 'phrase', 10, 1, 'approved', 0, ['where are you going', 'saan ka pupunta', 'asa ka padulong'], null],
    [7, 'kuon ki', 'Kain tayo', "Let's eat", 'Mangaon ta', 'phrase', 'phrase', 10, 1, 'approved', 0, ["let's eat", 'kain tayo', 'mangaon ta'], null],
    [8, 'kada idow', 'Araw-araw', 'Every day', 'Kada adlaw', 'phrase', 'phrase', 10, 1, 'approved', 0, ['every day', 'daily', 'araw araw', 'kada adlaw'], null],
    [9, 'pasaylo a', 'Paumanhin / Patawad', 'Sorry / Excuse me', 'Pasayloa ko', 'phrase', 'phrase', 10, 1, 'approved', 0, ['sorry', 'excuse me', 'patawad', 'paumanhin', 'pasaylo'], null],
    [10, 'ojow a', 'Ayoko', "I don't want", 'Dili ko', 'phrase', 'phrase', 10, 1, 'approved', 0, ["i don't want", 'ayoko', 'dili ko'], null],
    [11, 'buli a', 'Pabili', 'May I buy', 'Pabili / Palihug pabaligya', 'phrase', 'phrase', 10, 1, 'approved', 0, ['may i buy', 'pabili'], null],
    [12, 'ganina', 'Kanina', 'Earlier', 'Ganiha', 'time', 'word', 1, 1, 'approved', 0, ['earlier', 'kanina', 'ganiha'], null],
    [13, 'kasim', 'Bukas', 'Tomorrow', 'Ugma', 'time', 'word', 1, 1, 'approved', 0, ['tomorrow', 'bukas', 'ugma'], null],
    [14, 'idow', 'Araw', 'Day', 'Adlaw', 'time', 'word', 1, 1, 'approved', 0, ['day', 'araw', 'adlaw', 'sun'], null],
    [15, 'kuntoon', 'Ngayon', 'Now / Today', 'Karon', 'time', 'word', 1, 1, 'approved', 0, ['now', 'today', 'ngayon', 'karon'], null],
    [16, 'gaja', 'Mamaya', 'Later', 'Unya', 'time', 'word', 1, 1, 'approved', 0, ['later', 'mamaya', 'unya'], null],
    [17, 'gabi i', 'Kahapon', 'Yesterday', 'Gahapon', 'time', 'word', 1, 1, 'approved', 0, ['yesterday', 'kahapon', 'gahapon'], null],
    [18, 'masim', 'Umaga', 'Morning', 'Buntag', 'time', 'word', 1, 1, 'approved', 0, ['morning', 'umaga', 'buntag'], null],
    [19, 'maudto', 'Tanghali', 'Noon', 'Udto', 'time', 'word', 1, 1, 'approved', 0, ['noon', 'midday', 'tanghali', 'udto'], null],
    [20, 'mahapon', 'Hapon', 'Afternoon', 'Hapon', 'time', 'word', 1, 1, 'approved', 0, ['afternoon', 'hapon'], null],
    [21, 'madukilom', 'Gabi', 'Night / Evening', 'Gabii', 'time', 'word', 1, 1, 'approved', 0, ['night', 'evening', 'gabi', 'gabii'], null],
    [22, 'tuig', 'Taon', 'Year', 'Tuig', 'time', 'word', 1, 1, 'approved', 0, ['year', 'taon', 'tuig'], null],
    [23, 'tagad', 'Sandali / Maghintay', 'Wait / A moment', 'Hulat / Kadiyot', 'verb', 'word', 1, 1, 'approved', 0, ['wait', 'a moment', 'sandali', 'maghintay', 'hulat'], null],
    [24, 'sajo', 'Maaga', 'Early', 'Sayo', 'time', 'word', 1, 1, 'approved', 0, ['early', 'maaga', 'sayo'], null],
    [25, 'pirmi', 'Palagi', 'Always', 'Kanunay / Pirme', 'time', 'word', 1, 1, 'approved', 0, ['always', 'palagi', 'pirme', 'kanunay'], null],
    [26, 'usahay', 'Minsan', 'Sometimes', 'Usahay', 'time', 'word', 1, 1, 'approved', 0, ['sometimes', 'minsan', 'usahay'], null],
    [27, 'kagan o', 'Kailan', 'When', 'Kanus-a', 'question', 'word', 1, 1, 'approved', 0, ['when', 'kailan', 'kanus-a'], null],
    [28, 'ande', 'Saan', 'Where', 'Asa', 'question', 'word', 1, 1, 'approved', 0, ['where', 'saan', 'asa'], null],
    [29, 'nakoy', 'Bakit', 'Why', 'Ngano', 'question', 'word', 1, 1, 'approved', 0, ['why', 'bakit', 'ngano'], null],
    [30, 'amunohon', 'Paano', 'How', 'Unsaon', 'question', 'word', 1, 1, 'approved', 0, ['how', 'paano', 'unsaon'], null],
    [31, 'intawa', 'Sino', 'Who', 'Kinsa', 'question', 'word', 1, 1, 'approved', 0, ['who', 'sino', 'kinsa'], null],
    [32, 'siak', 'Ako', 'I / Me', 'Ako', 'pronoun', 'word', 1, 1, 'approved', 0, ['i', 'me', 'ako'], null],
    [33, 'sikuna', 'Ikaw', 'You', 'Ikaw', 'pronoun', 'word', 1, 1, 'approved', 0, ['you', 'ikaw'], null],
    [34, 'sikandin', 'Siya', 'He / She', 'Siya', 'pronoun', 'word', 1, 1, 'approved', 0, ['he', 'she', 'siya'], null],
    [35, 'sikandan', 'Sila', 'They', 'Sila', 'pronoun', 'word', 1, 1, 'approved', 0, ['they', 'them', 'sila'], null],
    [36, 'ita', 'Tayo', 'We (inclusive)', 'Kita', 'pronoun', 'word', 1, 1, 'approved', 0, ['we', 'tayo', 'kita'], null],
    [37, 'kanay', 'Sa akin', 'Mine / To me', 'Sa akoa', 'pronoun', 'word', 1, 1, 'approved', 0, ['mine', 'to me', 'sa akin', 'sa akoa'], null],
    [38, 'madaog', 'Marami / Madami', 'Many / A lot', 'Daghan', 'adjective', 'word', 1, 1, 'approved', 0, ['many', 'a lot', 'marami', 'madami', 'daghan'], null],
    [39, 'wada', 'Wala', 'None / Nothing', 'Wala', 'other', 'word', 1, 1, 'approved', 0, ['none', 'nothing', 'wala'], null],
    [40, 'medoon', 'Mayroon / Meron', 'There is / Have', 'Aduna / Naa', 'other', 'word', 1, 1, 'approved', 0, ['there is', 'have', 'mayroon', 'meron', 'naa', 'aduna'], null],
    [41, 'inggad', 'Kahit', 'Even / Even if', 'Bisan', 'connector', 'word', 1, 1, 'approved', 0, ['even', 'even if', 'kahit', 'bisan'], null],
    [42, 'yagboy', 'Talaga', 'Really / Truly', 'Tinuod / Gayud', 'other', 'word', 1, 1, 'approved', 0, ['really', 'truly', 'talaga', 'gayud', 'tinuod'], null],
    [43, 'kailing', 'Akala', 'Thought (assumed)', 'Abi', 'other', 'word', 1, 1, 'approved', 0, ['thought', 'akala', 'abi'], null],
    [44, 'huo', 'Oo', 'Yes', 'Oo', 'other', 'word', 1, 1, 'approved', 0, ['yes', 'oo'], null],
    [45, 'kuna', 'Hindi', 'No / Not', 'Dili', 'other', 'word', 1, 1, 'approved', 0, ['no', 'not', 'hindi', 'dili'], null],
    [46, 'ojow', 'Ayaw', "Don't want / Refuse", 'Dili gusto / Balibad', 'verb', 'word', 1, 1, 'approved', 0, ["don't want", 'refuse', 'ayaw', 'dili gusto'], null],
    [47, 'madujow', 'Mabuti', 'Good / Fine', 'Maayo', 'adjective', 'word', 1, 1, 'approved', 0, ['good', 'fine', 'mabuti', 'maayo'], null],
    [48, 'buotan', 'Mabait', 'Kind', 'Buotan', 'adjective', 'word', 1, 1, 'approved', 0, ['kind', 'good-natured', 'mabait', 'buotan'], null],
    [49, 'magwapa', 'Maganda', 'Beautiful', 'Gwapa / Nindot', 'adjective', 'word', 1, 1, 'approved', 0, ['beautiful', 'pretty', 'maganda', 'gwapa'], null],
    [50, 'maduot', 'Pangit', 'Ugly', 'Bati', 'adjective', 'word', 1, 1, 'approved', 0, ['ugly', 'pangit', 'bati'], null],
    [51, 'masamok', 'Maingay', 'Noisy', 'Saba', 'adjective', 'word', 1, 2, 'approved', 0, ['noisy', 'maingay', 'saba'], null],
    [52, 'mahumot', 'Mabango', 'Fragrant', 'Humot', 'adjective', 'word', 1, 2, 'approved', 0, ['fragrant', 'mabango', 'humot'], null],
    [53, 'madogi', 'Malaki', 'Big / Large', 'Dako', 'adjective', 'word', 1, 2, 'approved', 0, ['big', 'large', 'malaki', 'dako'], null],
    [54, 'ma intok', 'Maliit', 'Small', 'Gamay', 'adjective', 'word', 1, 2, 'approved', 0, ['small', 'little', 'maliit', 'gamay'], null],
    [55, 'masungot', 'Mabaho', 'Smelly', 'Baho', 'adjective', 'word', 1, 2, 'approved', 0, ['smelly', 'stinky', 'mabaho', 'baho'], null],
    [56, 'madani', 'Malapit', 'Near', 'Duol', 'adjective', 'word', 1, 2, 'approved', 0, ['near', 'close', 'malapit', 'duol'], null],
    [57, 'madiyo', 'Malayo', 'Far', 'Layo', 'adjective', 'word', 1, 2, 'approved', 0, ['far', 'distant', 'malayo', 'layo'], null],
    [58, 'nalipay', 'Masaya', 'Happy', 'Malipayon', 'adjective', 'word', 1, 2, 'approved', 0, ['happy', 'glad', 'masaya', 'malipayon'], null],
    [59, 'pahagtong', 'Tahimik', 'Quiet', 'Hilom', 'adjective', 'word', 1, 2, 'approved', 0, ['quiet', 'tahimik', 'hilom'], "Also: mahagtong, mahunok — confirm which is most used"],
    [60, 'mahagtong', 'Tahimik', 'Quiet', 'Hilom', 'adjective', 'word', 1, 2, 'approved', 0, ['quiet', 'tahimik', 'hilom'], "Also: pahagtong, mahunok"],
    [61, 'mahunok', 'Tahimik', 'Quiet / Calm', 'Hilom / Kalma', 'adjective', 'word', 1, 2, 'approved', 0, ['quiet', 'calm', 'tahimik', 'hilom'], "Also: pahagtong, mahagtong"],
    [62, 'matikang', 'Mataas', 'Tall / High', 'Taas', 'adjective', 'word', 1, 2, 'approved', 0, ['tall', 'high', 'mataas', 'taas'], null],
    [63, 'masagkop', 'Maikli / Pandak', 'Short', 'Mubo', 'adjective', 'word', 1, 2, 'approved', 0, ['short', 'maikli', 'pandak', 'mubo'], "Also: majupot (maikli)"],
    [64, 'majupot', 'Maikli', 'Short (length)', 'Mubo', 'adjective', 'word', 1, 2, 'approved', 0, ['short', 'maikli', 'mubo'], null],
    [65, 'kogihan', 'Masipag', 'Hardworking', 'Kugihan', 'adjective', 'word', 1, 2, 'approved', 0, ['hardworking', 'industrious', 'masipag', 'kugihan'], null],
    [66, 'poluho', 'Tamad', 'Lazy', 'Tapulan', 'adjective', 'word', 1, 2, 'approved', 0, ['lazy', 'tamad', 'tapulan'], null],
    [67, 'maduson', 'Malakas', 'Strong', 'Kusgan', 'adjective', 'word', 1, 2, 'approved', 0, ['strong', 'powerful', 'malakas', 'kusgan'], null],
    [68, 'mayutoy', 'Mahina', 'Weak', 'Luya', 'adjective', 'word', 1, 2, 'approved', 0, ['weak', 'mahina', 'luya'], null],
    [69, 'mabuyot', 'Matapang', 'Brave', 'Isog', 'adjective', 'word', 1, 2, 'approved', 0, ['brave', 'courageous', 'matapang', 'isog'], null],
    [70, 'masikawon', 'Mahiyain', 'Shy', 'Maulawon', 'adjective', 'word', 1, 2, 'approved', 0, ['shy', 'timid', 'mahiyain', 'maulawon'], null],
    [71, 'daotan', 'Masama', 'Bad / Evil', 'Dautan', 'adjective', 'word', 1, 2, 'approved', 0, ['bad', 'evil', 'masama', 'dautan'], null],
    [72, 'magol anon', 'Malungkot', 'Sad', 'Masulob-on', 'adjective', 'word', 1, 2, 'approved', 0, ['sad', 'sorrowful', 'malungkot', 'masulob-on'], null],
    [73, 'uhawon', 'Nauuhaw / Uhaw', 'Thirsty', 'Giuhaw', 'adjective', 'word', 1, 2, 'approved', 0, ['thirsty', 'uhaw', 'nauuhaw', 'giuhaw'], null],
    [74, 'mahinlo', 'Malinis', 'Clean', 'Limpyo', 'adjective', 'word', 1, 2, 'approved', 0, ['clean', 'pure', 'malinis', 'limpyo'], null],
    [75, 'maligsom', 'Marumi', 'Dirty', 'Hugaw', 'adjective', 'word', 1, 2, 'approved', 0, ['dirty', 'soiled', 'marumi', 'hugaw'], null],
    [76, 'tahay', 'Tuyo', 'Dry', 'Uga', 'adjective', 'word', 1, 2, 'approved', 0, ['dry', 'tuyo', 'uga'], null],
    [77, 'basa', 'Basa', 'Wet', 'Basa', 'adjective', 'word', 1, 2, 'approved', 0, ['wet', 'basa'], null],
    [78, 'mabug at', 'Mabigat', 'Heavy', 'Bug-at', 'adjective', 'word', 1, 3, 'approved', 0, ['heavy', 'mabigat', 'bug-at'], null],
    [79, 'maagkap', 'Magaan', 'Light (weight)', 'Gaan', 'adjective', 'word', 1, 3, 'approved', 0, ['light', 'lightweight', 'magaan', 'gaan'], null],
    [80, 'mapaso', 'Mainit', 'Hot', 'Init', 'adjective', 'word', 1, 3, 'approved', 0, ['hot', 'warm', 'mainit', 'init'], null],
    [81, 'matignaw', 'Malamig', 'Cold', 'Bugnaw', 'adjective', 'word', 1, 3, 'approved', 0, ['cold', 'chilly', 'malamig', 'bugnaw'], null],
    [82, 'mapudos', 'Maasim', 'Sour', 'Aslom', 'adjective', 'word', 1, 3, 'approved', 0, ['sour', 'tart', 'maasim', 'aslom'], null],
    [83, 'matam is', 'Matamis', 'Sweet', 'Tamis', 'adjective', 'word', 1, 3, 'approved', 0, ['sweet', 'matamis', 'tam-is'], null],
    [84, 'mapoit', 'Mapait', 'Bitter', 'Pait', 'adjective', 'word', 1, 3, 'approved', 0, ['bitter', 'mapait', 'pait'], null],
    [85, 'maparat', 'Maalat', 'Salty', 'Parat', 'adjective', 'word', 1, 3, 'approved', 0, ['salty', 'maalat', 'parat'], null],
    [86, 'malami', 'Masarap', 'Delicious', 'Lami', 'adjective', 'word', 1, 3, 'approved', 0, ['delicious', 'tasty', 'masarap', 'lami'], null],
    [87, 'hilow', 'Hilaw', 'Raw / Unripe', 'Hilaw', 'adjective', 'word', 1, 3, 'approved', 0, ['raw', 'unripe', 'hilaw'], null],
    [88, 'mapaspas', 'Mabilis', 'Fast', 'Kusog / Paspas', 'adjective', 'word', 1, 3, 'approved', 0, ['fast', 'quick', 'mabilis', 'paspas'], null],
    [89, 'mahinoy', 'Mabagal', 'Slow', 'Hinay', 'adjective', 'word', 1, 3, 'approved', 0, ['slow', 'mabagal', 'hinay'], null],
    [90, 'mayow ag', 'Malawak', 'Wide', 'Halapad', 'adjective', 'word', 1, 3, 'approved', 0, ['wide', 'broad', 'malawak', 'halapad'], null],
    [91, 'matagsa', 'Kaunti', 'Few / A little', 'Diyutay / Gamay', 'adjective', 'word', 1, 3, 'approved', 0, ['few', 'a little', 'kaunti', 'gamay'], null],
    [92, 'tibo', 'Lahat', 'All / Everyone', 'Tanan', 'other', 'word', 1, 3, 'approved', 0, ['all', 'everyone', 'lahat', 'tanan'], null],
    [93, 'maawang', 'Malinaw', 'Clear', 'Tin-aw', 'adjective', 'word', 1, 4, 'approved', 0, ['clear', 'malinaw', 'tin-aw'], null],
    [94, 'naguba', 'Nasira', 'Broken / Destroyed', 'Naguba', 'verb', 'word', 1, 1, 'approved', 0, ['broken', 'destroyed', 'nasira', 'naguba'], null],
    [95, 'lipodong', 'Natulog', 'Slept / Sleep', 'Natulog', 'verb', 'word', 1, 1, 'approved', 0, ['slept', 'sleep', 'natulog'], null],
    [96, 'buyat', 'Gising', 'Awake / Wake up', 'Mata / Pukaw', 'verb', 'word', 1, 1, 'approved', 0, ['awake', 'wake up', 'gising', 'mata'], null],
    [97, 'indoy', 'Iwan', 'Leave behind', 'Biyai', 'verb', 'word', 1, 1, 'approved', 0, ['leave', 'leave behind', 'iwan', 'biyai'], null],
    [98, 'kuon', 'Kain', 'Eat', 'Kaon', 'verb', 'word', 1, 1, 'approved', 0, ['eat', 'kain', 'kaon'], null],
    [99, 'mig tabang', 'Tumulong', 'Helped / Help', 'Nitabang', 'verb', 'word', 1, 2, 'approved', 0, ['helped', 'help', 'tumulong', 'nitabang'], null],
    [100, 'mig hunahuna', 'Nag-iisip', 'Thinking', 'Naghunahuna', 'verb', 'word', 1, 2, 'approved', 0, ['thinking', 'nag-iisip', 'naghunahuna'], null],
    [101, 'paminog', 'Makinig', 'Listen', 'Paminaw', 'verb', 'word', 1, 2, 'approved', 0, ['listen', 'makinig', 'paminaw'], null],
    [102, 'mig ngangang', 'Umiiyak', 'Crying', 'Naghilak', 'verb', 'word', 1, 2, 'approved', 0, ['crying', 'umiiyak', 'naghilak'], null],
    [103, 'mig ngisi', 'Tumatawa', 'Laughing', 'Nagkatawa', 'verb', 'word', 1, 2, 'approved', 0, ['laughing', 'tumatawa', 'nagkatawa'], null],
    [104, 'sikad', 'Tumakbo', 'Run', 'Nidagan', 'verb', 'word', 1, 2, 'approved', 0, ['run', 'running', 'tumakbo', 'dagan'], null],
    [105, 'panow', 'Lakad', 'Walk', 'Lakaw', 'verb', 'word', 1, 2, 'approved', 0, ['walk', 'walking', 'lakad', 'lakaw'], null],
    [106, 'uli', 'Umuwi', 'Go home', 'Pauli', 'verb', 'word', 1, 2, 'approved', 0, ['go home', 'umuwi', 'pauli'], null],
    [107, 'sed', 'Pumasok', 'Enter / Come in', 'Sulod', 'verb', 'word', 1, 2, 'approved', 0, ['enter', 'come in', 'pumasok', 'sulod'], "Sense: enter / come in (verb); distinct from SIL preposition 'inside'"],
    [108, 'mig inom', 'Umiinom', 'Drinking', 'Nag-inom', 'verb', 'word', 1, 2, 'approved', 0, ['drinking', 'umiinom', 'nag-inom'], null],
    [109, 'sajow', 'Sumayaw', 'Dance', 'Sayaw', 'verb', 'word', 1, 3, 'approved', 0, ['dance', 'sumayaw', 'sayaw'], null],
    [110, 'mo storya', 'Magsalita', 'Speak / Talk', 'Mosulti / Mag-istorya', 'verb', 'word', 1, 3, 'approved', 0, ['speak', 'talk', 'magsalita', 'mosulti'], null],
    [111, 'mig aha', 'Tumingin', 'Look', 'Nitan-aw', 'verb', 'word', 1, 3, 'approved', 0, ['look', 'tumingin', 'nitan-aw'], null],
    [112, 'mig panow', 'Umalis', 'Leave / Depart', 'Nilakaw / Nilarga', 'verb', 'word', 1, 3, 'approved', 0, ['leave', 'depart', 'umalis', 'nilakaw'], null],
    [113, 'datung', 'Dumating', 'Arrive', 'Niabot', 'verb', 'word', 1, 3, 'approved', 0, ['arrive', 'dumating', 'niabot'], null],
    [114, 'yuto', 'Luto', 'Cook / Cooked', 'Luto', 'verb', 'word', 1, 3, 'approved', 0, ['cooked', 'luto'], null],
    [115, 'mig yuto', 'Magluto', 'Cook (to cook)', 'Magluto', 'verb', 'word', 1, 3, 'approved', 0, ['cook', 'magluto'], null],
    [116, 'pamugoy', 'Magbigay', 'Give', 'Mohatag', 'verb', 'word', 1, 3, 'approved', 0, ['give', 'magbigay', 'mohatag'], null],
    [117, 'pudot', 'Kumuha', 'Get / Take', 'Kuha', 'verb', 'word', 1, 3, 'approved', 0, ['get', 'take', 'kumuha', 'kuha'], null],
    [118, 'daya', 'Magdala', 'Bring / Carry', 'Dala', 'verb', 'word', 1, 3, 'approved', 0, ['bring', 'carry', 'magdala', 'dala'], null],
    [119, 'suyat', 'Sulat', 'Write / Letter', 'Sulat', 'verb', 'word', 1, 3, 'approved', 0, ['write', 'letter', 'sulat'], null],
    [120, 'pahuway', 'Pahinga', 'Rest', 'Pahuway', 'verb', 'word', 1, 3, 'approved', 0, ['rest', 'pahinga', 'pahuway'], null],
    [121, 'ajag', 'Mag-ingat', 'Take care', 'Pag-amping', 'verb', 'word', 1, 3, 'approved', 0, ['take care', 'mag-ingat', 'pag-amping'], null],
    [122, 'ingkud', 'Umupo', 'Sit', 'Lingkod', 'verb', 'word', 1, 3, 'approved', 0, ['sit', 'umupo', 'lingkod'], null],
    [123, 'tindog', 'Tumayo', 'Stand', 'Barog', 'verb', 'word', 1, 3, 'approved', 0, ['stand', 'tumayo', 'barog'], null],
    [124, 'yakso', 'Tumalon', 'Jump', 'Lukso', 'verb', 'word', 1, 3, 'approved', 0, ['jump', 'tumalon', 'lukso'], null],
    [125, 'pinadajag', 'Minamahal / Palangga', 'Beloved / Dear', 'Hinigugma / Pinangga', 'adjective', 'word', 1, 3, 'approved', 0, ['beloved', 'dear', 'minamahal', 'palangga', 'pinangga'], null],
    [126, 'bujag', 'Babae', 'Woman / Female', 'Babaye', 'noun', 'word', 1, 1, 'approved', 0, ['woman', 'female', 'babae', 'babaye'], null],
    [127, 'yukos', 'Lalaki', 'Man / Male', 'Lalaki', 'noun', 'word', 1, 1, 'approved', 0, ['man', 'male', 'lalaki'], null],
    [128, 'suon', 'Kapatid', 'Sibling', 'Igsuon', 'noun', 'word', 1, 2, 'approved', 0, ['sibling', 'brother', 'sister', 'kapatid', 'igsuon'], null],
    [129, 'bata', 'Anak', 'Child', 'Anak / Bata', 'noun', 'word', 1, 2, 'approved', 0, ['child', 'kid', 'anak', 'bata'], null],
    [130, 'maestra', 'Guro', 'Teacher', 'Maestra / Magtutudlo', 'noun', 'word', 1, 2, 'approved', 0, ['teacher', 'guro', 'maestra'], null],
    [131, 'ajo', 'Kaibigan', 'Friend', 'Higala', 'noun', 'word', 1, 2, 'approved', 0, ['friend', 'kaibigan', 'higala'], null],
    [132, 'duma', 'Kasama', 'Companion', 'Kauban', 'noun', 'word', 1, 5, 'approved', 0, ['companion', 'partner', 'kasama', 'kauban'], null],
    [133, 'ngadan', 'Pangalan', 'Name', 'Ngalan', 'noun', 'word', 1, 4, 'approved', 0, ['name', 'pangalan', 'ngalan'], null],
    [134, 'pahinomdom', 'Paalala', 'Reminder / Notice', 'Pahinumdom', 'noun', 'word', 1, 1, 'approved', 0, ['reminder', 'notice', 'paalala', 'pahinumdom'], null],
    [135, 'udan', 'Ulan', 'Rain', 'Ulan', 'nature', 'word', 1, 2, 'approved', 0, ['rain', 'ulan'], null],
    [136, 'bubungan', 'Bundok', 'Mountain', 'Bukid', 'nature', 'word', 1, 2, 'approved', 0, ['mountain', 'bundok', 'bukid'], null],
    [137, 'buyak', 'Bulaklak', 'Flower', 'Bulak', 'nature', 'word', 1, 2, 'approved', 0, ['flower', 'bulaklak', 'bulak'], null],
    [138, 'kajo', 'Puno', 'Tree', 'Kahoy', 'nature', 'word', 1, 2, 'approved', 0, ['tree', 'wood', 'puno', 'kahoy'], null],
    [139, 'yangit', 'Langit', 'Sky', 'Langit', 'nature', 'word', 1, 2, 'approved', 0, ['sky', 'heaven', 'langit'], null],
    [140, 'pasak', 'Lupa', 'Soil / Land', 'Yuta', 'nature', 'word', 1, 2, 'approved', 0, ['soil', 'land', 'earth', 'lupa', 'yuta'], null],
    [141, 'wuhig', 'Tubig', 'Water', 'Tubig', 'nature', 'word', 1, 2, 'approved', 0, ['water', 'tubig'], null],
    [142, 'duhon', 'Dahon', 'Leaf', 'Dahon', 'nature', 'word', 1, 3, 'approved', 0, ['leaf', 'dahon'], null],
    [143, 'guyangan', 'Gubat', 'Forest', 'Lasang', 'nature', 'word', 1, 3, 'approved', 0, ['forest', 'jungle', 'gubat', 'lasang'], null],
    [144, 'bagsak', 'Putik', 'Mud', 'Lapok', 'nature', 'word', 1, 3, 'approved', 0, ['mud', 'putik', 'lapok'], null],
    [145, 'kejo', 'Apoy', 'Fire', 'Kalayo', 'nature', 'word', 1, 3, 'approved', 0, ['fire', 'apoy', 'kalayo'], null],
    [146, 'ubey', 'Usok', 'Smoke', 'Aso', 'nature', 'word', 1, 3, 'approved', 0, ['smoke', 'usok', 'aso'], null],
    [147, 'kilat', 'Kidlat', 'Lightning', 'Kilat', 'nature', 'word', 1, 3, 'approved', 0, ['lightning', 'kidlat', 'kilat'], null],
    [148, 'yugong', 'Kulog', 'Thunder', 'Liti / Dugdug', 'nature', 'word', 1, 4, 'approved', 0, ['thunder', 'kulog', 'dugdug'], null],
    [149, 'bituo', 'Bituin', 'Star', 'Bitoon', 'nature', 'word', 1, 4, 'approved', 0, ['star', 'bituin', 'bitoon'], null],
    [150, 'buyan', 'Buwan', 'Moon / Month', 'Bulan', 'nature', 'word', 1, 4, 'approved', 0, ['moon', 'month', 'buwan', 'bulan'], null],
    [151, 'bagjo', 'Bagyo', 'Typhoon / Storm', 'Bagyo', 'nature', 'word', 1, 4, 'approved', 0, ['typhoon', 'storm', 'bagyo'], null],
    [152, 'yambong', 'Anino', 'Shadow', 'Landong / Anino', 'nature', 'word', 1, 4, 'approved', 0, ['shadow', 'anino', 'landong'], null],
    [153, 'babayoy', 'Baboy', 'Pig', 'Baboy', 'animal', 'word', 1, 2, 'approved', 0, ['pig', 'swine', 'baboy'], null],
    [154, 'kibow', 'Kalabaw', 'Carabao', 'Kabaw', 'animal', 'word', 1, 4, 'approved', 0, ['carabao', 'water buffalo', 'kalabaw', 'kabaw'], null],
    [155, 'amo', 'Unggoy', 'Monkey', 'Unggoy / Amo', 'animal', 'word', 1, 4, 'approved', 0, ['monkey', 'unggoy', 'amo'], null],
    [156, 'hayas', 'Ahas', 'Snake', 'Halas', 'animal', 'word', 1, 4, 'approved', 0, ['snake', 'serpent', 'ahas', 'halas'], null],
    [157, 'amabak', 'Palaka', 'Frog', 'Baki', 'animal', 'word', 1, 4, 'approved', 0, ['frog', 'palaka', 'baki'], null],
    [158, 'kabakaba', 'Paruparo', 'Butterfly', 'Alibangbang', 'animal', 'word', 1, 4, 'approved', 0, ['butterfly', 'paruparo', 'alibangbang'], null],
    [159, 'bayoy', 'Bahay', 'House', 'Balay', 'object', 'word', 1, 2, 'approved', 0, ['house', 'home', 'bahay', 'balay'], null],
    [160, 'kudak', 'Litrato', 'Photo', 'Hulagway / Litrato', 'object', 'word', 1, 4, 'approved', 0, ['photo', 'picture', 'litrato', 'hulagway'], null],
    [161, 'ispiho', 'Salamin', 'Mirror', 'Salamin', 'object', 'word', 1, 4, 'approved', 0, ['mirror', 'glass', 'salamin'], null],
    [162, 'yabe', 'Susi', 'Key', 'Yawi', 'object', 'word', 1, 4, 'approved', 0, ['key', 'susi', 'yawi'], null],
    [163, 'yubon', 'Kumot', 'Blanket', 'Habol', 'object', 'word', 1, 4, 'approved', 0, ['blanket', 'kumot', 'habol'], null],
    [164, 'sudlay', 'Suklay', 'Comb', 'Sudlay', 'object', 'word', 1, 4, 'approved', 0, ['comb', 'suklay', 'sudlay'], null],
    [165, 'pajong', 'Payong', 'Umbrella', 'Payong', 'object', 'word', 1, 4, 'approved', 0, ['umbrella', 'payong'], null],
    [166, 'kandido', 'Kaldero', 'Cooking pot', 'Kaldiro / Kolon', 'object', 'word', 1, 4, 'approved', 0, ['cooking pot', 'pot', 'kaldero', 'kaldiro'], null],
    [167, 'silhig', 'Walis', 'Broom', 'Silhig', 'object', 'word', 1, 4, 'approved', 0, ['broom', 'walis', 'silhig'], null],
    [168, 'dagom', 'Karayom', 'Needle', 'Dagom', 'object', 'word', 1, 4, 'approved', 0, ['needle', 'karayom', 'dagom'], null],
    [169, 'dayan', 'Daan', 'Road / Path', 'Dalan', 'object', 'word', 1, 5, 'approved', 0, ['road', 'path', 'way', 'daan', 'dalan'], null],
    [170, 'e an', 'Uman', '?', 'Uman', 'other', 'word', 1, 4, 'pending_review', 1, ['uman'], 'UNCLEAR in PDF — please verify meaning'],
    [171, 'uyo', 'Ulo', 'Head', 'Ulo', 'body', 'word', 1, 4, 'approved', 0, ['head', 'ulo'], null],
    [172, 'simod', 'Ilong', 'Nose', 'Ilong', 'body', 'word', 1, 4, 'approved', 0, ['nose', 'ilong'], null],
    [173, 'mata', 'Mata', 'Eye', 'Mata', 'body', 'word', 1, 4, 'approved', 0, ['eye', 'eyes', 'mata'], 'Sense: eye / eyes (body)'],
    [174, 'baba', 'Bibig', 'Mouth', 'Baba', 'body', 'word', 1, 4, 'approved', 0, ['mouth', 'bibig', 'baba'], null],
    [175, 'ngipon', 'Ngipin', 'Tooth / Teeth', 'Ngipon', 'body', 'word', 1, 4, 'approved', 0, ['tooth', 'teeth', 'ngipin', 'ngipon'], null],
    [176, 'talinga', 'Tainga', 'Ear', 'Dalunggan', 'body', 'word', 1, 4, 'approved', 0, ['ear', 'ears', 'tainga', 'dalunggan'], null],
    [177, 'buyad', 'Kamay', 'Hand', 'Kamot', 'body', 'word', 1, 4, 'approved', 0, ['hand', 'hands', 'kamay', 'kamot'], null],
    [178, 'kubong', 'Paa', 'Foot', 'Tiil', 'body', 'word', 1, 4, 'approved', 0, ['foot', 'feet', 'paa', 'tiil'], null],
    [179, 'abaga', 'Balikat', 'Shoulder', 'Abaga', 'body', 'word', 1, 4, 'approved', 0, ['shoulder', 'balikat', 'abaga'], null],
    [180, 'bagakwang', 'Likod', 'Back', 'Likod', 'body', 'word', 1, 4, 'approved', 0, ['back', 'likod'], null],
    [181, 'gutok', 'Tiyan', 'Stomach', 'Tiyan', 'body', 'word', 1, 4, 'approved', 0, ['stomach', 'belly', 'tiyan'], null],
    [182, 'dagiha', 'Dibdib', 'Chest', 'Dughan', 'body', 'word', 1, 4, 'approved', 0, ['chest', 'breast', 'dibdib', 'dughan'], null],
    [183, 'kasing kasing', 'Puso', 'Heart', 'Kasingkasing', 'body', 'phrase', 10, 4, 'approved', 0, ['heart', 'puso', 'kasingkasing'], "PDF says 'Piso' — typo for 'Puso'"],
    [184, 'yangosa', 'Dugo', 'Blood', 'Dugo', 'body', 'word', 1, 4, 'approved', 0, ['blood', 'dugo'], null],
    [185, 'pale', 'Sugat', 'Wound', 'Samad', 'body', 'word', 1, 4, 'approved', 0, ['wound', 'cut', 'sugat', 'samad'], null],
    [186, 'yuha', 'Luha', 'Tear(s)', 'Luha', 'body', 'word', 1, 4, 'approved', 0, ['tear', 'tears', 'luha'], null],
    [187, 'hingoyow', 'Lagnat', 'Fever', 'Hilanat', 'body', 'word', 1, 4, 'approved', 0, ['fever', 'lagnat', 'hilanat'], null],
    [188, 'pahiyom', 'Ngiti', 'Smile', 'Pahiyom', 'emotion', 'word', 1, 4, 'approved', 0, ['smile', 'ngiti', 'pahiyom'], null],
    [189, 'ngisi', 'Tawa', 'Laugh', 'Katawa', 'emotion', 'word', 1, 4, 'approved', 0, ['laugh', 'laughter', 'tawa', 'katawa'], null],
    [190, 'yangut', 'Galit', 'Angry / Anger', 'Kasuko / Suko', 'emotion', 'word', 1, 2, 'approved', 0, ['angry', 'anger', 'galit', 'suko'], 'Variant spelling: yangot'],
    [191, 'yangot', 'Galit', 'Anger', 'Kasuko', 'emotion', 'word', 1, 5, 'approved', 0, ['anger', 'galit', 'kasuko'], 'Variant spelling: yangut'],
    [192, 'kahidok', 'Takot', 'Afraid', 'Kahadlok', 'emotion', 'word', 1, 2, 'approved', 0, ['afraid', 'scared', 'takot', 'nahadlok'], null],
    [193, 'hidok', 'Takot', 'Fear', 'Hadlok', 'emotion', 'word', 1, 5, 'approved', 0, ['fear', 'takot', 'hadlok'], null],
    [194, 'kayutoy', 'Pagod', 'Tired', 'Kapoy', 'emotion', 'word', 1, 2, 'approved', 0, ['tired', 'fatigued', 'pagod', 'kapoy'], null],
    [195, 'kabuntas', 'Gutom', 'Hungry', 'Gutom', 'emotion', 'word', 1, 2, 'approved', 0, ['hungry', 'gutom'], null],
    [196, 'kalipay', 'Saya', 'Happiness / Joy', 'Kalipay', 'emotion', 'word', 1, 5, 'approved', 0, ['happiness', 'joy', 'saya', 'tuwa', 'kalipay'], null],
    [197, 'kasamok', 'Ingay', 'Noise', 'Kasamok / Saba', 'noun', 'word', 1, 5, 'approved', 0, ['noise', 'trouble', 'ingay', 'kasamok', 'saba'], null],
    [198, 'kamatajon', 'Kamatayan', 'Death', 'Kamatayon', 'noun', 'word', 1, 5, 'approved', 0, ['death', 'kamatayan', 'kamatayon'], null],
    [199, 'tubag', 'Sagot', 'Answer', 'Tubag', 'noun', 'word', 1, 5, 'approved', 0, ['answer', 'reply', 'sagot', 'tubag'], null],
    [200, 'pangusip', 'Tanong', 'Question', 'Pangutana', 'noun', 'word', 1, 5, 'approved', 0, ['question', 'tanong', 'pangutana'], null],
    [201, 'sugilon', 'Kuwento', 'Story', 'Sugilanon / Istorya', 'noun', 'word', 1, 5, 'approved', 0, ['story', 'tale', 'kuwento', 'kwento', 'sugilanon'], null],
    [202, 'pangandoy', 'Pangarap', 'Dream / Aspiration', 'Pangandoy', 'noun', 'word', 1, 5, 'approved', 0, ['dream', 'aspiration', 'pangarap', 'pangandoy'], null],
    [203, 'hinungdan', 'Dahilan', 'Reason', 'Hinungdan', 'noun', 'word', 1, 5, 'approved', 0, ['reason', 'cause', 'dahilan', 'hinungdan'], null],
    [204, 'tabang', 'Tulong', 'Help (noun)', 'Tabang', 'noun', 'word', 1, 5, 'approved', 0, ['help', 'assistance', 'tulong', 'tabang'], null],
    [205, 'tambag', 'Payo', 'Advice', 'Tambag', 'noun', 'word', 1, 5, 'approved', 0, ['advice', 'counsel', 'payo', 'tambag'], null],
    [206, 'kaagi', 'Karanasan', 'Experience', 'Kaagi', 'noun', 'word', 1, 5, 'approved', 0, ['experience', 'past', 'karanasan', 'kaagi'], null],
    [207, 'pagkahuman', 'Pagkatapos', 'After', 'Pagkahuman', 'connector', 'word', 1, 4, 'approved', 0, ['after', 'afterwards', 'pagkatapos', 'pagkahuman'], null],
    [208, 'samtang', 'Habang', 'While', 'Samtang', 'connector', 'word', 1, 5, 'approved', 0, ['while', 'habang', 'samtang'], null],
    [209, 'so', 'Dahil', 'Because', 'Tungod kay', 'connector', 'word', 1, 5, 'approved', 0, ['because', 'dahil', 'tungod kay'], null],
    [210, 'pero', 'Ngunit / Pero', 'But', 'Apan / Pero', 'connector', 'word', 1, 5, 'approved', 0, ['but', 'however', 'ngunit', 'pero', 'apan'], null],
    [211, 'to', 'Kung', 'If', 'Kung', 'connector', 'word', 1, 5, 'approved', 0, ['if', 'kung'], null],
    [212, 'pag', 'Kapag', 'When (if)', 'Kung / Inig', 'connector', 'word', 1, 5, 'approved', 0, ['when', 'kapag', 'inig'], null],
    [213, 'para', 'Para', 'For', 'Para', 'connector', 'word', 1, 5, 'approved', 0, ['for', 'in order to', 'para'], null],
    [214, 'subuokon', 'Mag-isa', 'Alone', 'Nag-inusara', 'other', 'word', 1, 5, 'approved', 0, ['alone', 'solitary', 'mag-isa', 'nag-inusara'], null],
    [215, 'atubang', 'Harap', 'Front', 'Atubangan', 'direction', 'word', 1, 5, 'approved', 0, ['front', 'harap', 'atubangan'], null],
    [216, 'didayom', 'Loob', 'Inside', 'Sulod', 'direction', 'word', 1, 5, 'approved', 0, ['inside', 'within', 'loob', 'sulod'], null],
    [217, 'gawas', 'Labas', 'Outside', 'Gawas', 'direction', 'word', 1, 5, 'approved', 0, ['outside', 'labas', 'gawas'], null],
    [218, 'babow', 'Ibabaw', 'Above / On top', 'Ibabaw', 'direction', 'word', 1, 5, 'approved', 0, ['above', 'on top', 'ibabaw'], null],
    [219, 'kawey', 'Kaliwa', 'Left', 'Wala', 'direction', 'word', 1, 5, 'approved', 0, ['left', 'kaliwa', 'wala'], null],
    [220, 'kalintoo', 'Kanan', 'Right', 'Tuo', 'direction', 'word', 1, 5, 'approved', 0, ['right', 'kanan', 'tuo'], null],
    [221, 'tapad', 'Tabi', 'Beside', 'Tupad', 'direction', 'word', 1, 5, 'approved', 0, ['beside', 'next to', 'tabi', 'tupad'], null],
    [222, 'pagtungaan', 'Gitna', 'Middle', 'Tunga', 'direction', 'word', 1, 5, 'approved', 0, ['middle', 'center', 'gitna', 'tunga'], null],
    [223, 'tampos', 'Dulo', 'End / Edge', 'Tumoy', 'direction', 'word', 1, 5, 'approved', 0, ['end', 'edge', 'tip', 'dulo', 'tumoy'], null],
    [224, 'kani', 'Dito', 'Here', 'Diri / Dinhi', 'direction', 'word', 1, 3, 'approved', 0, ['here', 'dito', 'diri', 'dinhi'], null],
    [225, 'diya', 'Doon', 'There', 'Didto', 'direction', 'word', 1, 3, 'approved', 0, ['there', 'doon', 'didto'], null],
    [226, 'sinugdan', 'Simula', 'Beginning', 'Sinugdanan', 'other', 'word', 1, 5, 'approved', 0, ['beginning', 'start', 'simula', 'sinugdanan'], null],
    [227, 'kataposan', 'Wakas', 'End / Conclusion', 'Kataposan', 'other', 'word', 1, 5, 'approved', 0, ['end', 'conclusion', 'wakas', 'kataposan'], null],
    [228, 'una', 'Una', 'First', 'Una', 'other', 'word', 1, 5, 'approved', 0, ['first', 'una', 'unang'], null],
    [229, 'hudi', 'Huli', 'Last', 'Ulahing / Katapusan', 'other', 'word', 1, 5, 'approved', 0, ['last', 'huli', 'katapusan'], null],
    [230, 'aangod', 'Pareho', 'Same', 'Pareho / Sama', 'other', 'word', 1, 5, 'approved', 0, ['same', 'similar', 'pareho', 'sama'], null],
    [231, 'lahi', 'Iba', 'Different', 'Lahi', 'other', 'word', 1, 5, 'approved', 0, ['different', 'other', 'iba', 'lahi'], null],
    [232, 'tinood', 'Totoo', 'True', 'Tinuod', 'other', 'word', 1, 5, 'approved', 0, ['true', 'real', 'totoo', 'tinuod'], null],
    [233, 'sakto', 'Tama', 'Correct', 'Husto / Sakto', 'other', 'word', 1, 5, 'approved', 0, ['correct', 'right', 'tama', 'sakto', 'husto'], null],
    [234, 'mali', 'Mali', 'Wrong', 'Sayop', 'other', 'word', 1, 5, 'approved', 0, ['wrong', 'mistake', 'mali', 'sayop'], null],
];

    $isPgsql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');

    $countInserted = 0;
    $countUpdated  = 0;

    foreach ($dataset as $item) {
        [$pdfId, $manobo, $tagalog, $english, $bisaya, $category, $type, $priority, $page, $reviewStatus, $needsReview, $aliasesArr, $notes] = $item;

        $normTagalog = seed_manobo_normaliseText($tagalog);
        $normEnglish = seed_manobo_normaliseText($english);
        $normBisaya  = seed_manobo_normaliseText($bisaya);
        $aliasesJson = json_encode($aliasesArr, JSON_UNESCAPED_UNICODE);

    // Look for existing row with this headword and source='Manobo Words.pdf' or 'USER-2026'
    $stmt = $pdo->prepare(
        "SELECT id FROM manobo_dictionary 
         WHERE LOWER(TRIM(manobo)) = LOWER(TRIM(?)) 
           AND (source = 'Manobo Words.pdf' OR source = 'USER-2026')
         LIMIT 1"
    );
    $stmt->execute([$manobo]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $updateStmt = $pdo->prepare(
            "UPDATE manobo_dictionary SET
                tagalog = ?,
                english = ?,
                bisaya = ?,
                normalized_tagalog = ?,
                normalized_english = ?,
                normalized_bisaya = ?,
                category = ?,
                type = ?,
                priority = ?,
                source_page = ?,
                review_status = ?,
                needs_review = ?,
                aliases = ?,
                notes = ?,
                source = 'Manobo Words.pdf',
                updated_at = NOW()
             WHERE id = ?"
        );
        $updateStmt->execute([
            $tagalog, $english, $bisaya,
            $normTagalog, $normEnglish, $normBisaya,
            $category, $type, $priority, $page,
            $reviewStatus, $needsReview, $aliasesJson,
            $notes, $existingId
        ]);
        $countUpdated++;
    } else {
        $insertStmt = $pdo->prepare(
            "INSERT INTO manobo_dictionary
                (manobo, tagalog, english, bisaya,
                 normalized_tagalog, normalized_english, normalized_bisaya,
                 category, type, priority, source_page,
                 review_status, needs_review, aliases, notes, source, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Manobo Words.pdf', NOW(), NOW())"
        );
        $insertStmt->execute([
            $manobo, $tagalog, $english, $bisaya,
            $normTagalog, $normEnglish, $normBisaya,
            $category, $type, $priority, $page,
            $reviewStatus, $needsReview, $aliasesJson, $notes
        ]);
        $countInserted++;
    }
}

    // Also update normalized fields for any remaining SIL / WIKT rows
    $stmt = $pdo->query("SELECT id, tagalog, english, bisaya FROM manobo_dictionary WHERE normalized_tagalog IS NULL OR normalized_english IS NULL");
    $allRemaining = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $normStmt = $pdo->prepare("UPDATE manobo_dictionary SET normalized_tagalog = ?, normalized_english = ?, normalized_bisaya = ? WHERE id = ?");
    foreach ($allRemaining as $r) {
        $normStmt->execute([
            seed_manobo_normaliseText($r['tagalog'] ?? ''),
            seed_manobo_normaliseText($r['english'] ?? ''),
            seed_manobo_normaliseText($r['bisaya'] ?? ''),
            $r['id']
        ]);
    }

    $total = (int) $pdo->query('SELECT COUNT(*) FROM manobo_dictionary')->fetchColumn();
    return ['inserted' => $countInserted, 'updated' => $countUpdated, 'total' => $total];
}

if (php_sapi_name() === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    chdir(__DIR__ . '/..');
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../app/helpers.php';
    $res = seed_manobo_pdf_dataset(db());
    echo "Finished seeding: {$res['inserted']} inserted, {$res['updated']} updated. Total entries in manobo_dictionary: {$res['total']}\n";
}
