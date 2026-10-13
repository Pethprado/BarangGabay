<?php
declare(strict_types=1);

/**
 * Current sample content for BarangGabay (written 2026-10-13).
 *
 * Every post is written by hand in all three languages, so pressing EN, FIL
 * or MN shows a complete, correct version instead of a machine guess:
 *
 *   fil — the original (source_lang = 'fil')
 *   en  — English
 *   mn  — written in Bisaya/Cebuano; tools/seed_current_content.php swaps in
 *         the Manobo word for every term the barangay's Manobo dataset knows
 *         (ManoboHybridTranslator::refineBisayaWithManobo), so it reads
 *         Manobo first and Bisaya only where the dataset has no word.
 *
 * National facts are current as of October 2026 and attributed to their
 * source (PAGASA, DOST, DOH, COMELEC, Proclamation No. 1006 s. 2025,
 * RA 12326). Anything barangay-level — dates, venues, ordinance numbers in
 * Bayogo — is illustrative: every row is is_sample = 1, every cover carries a
 * SAMPLE tag, and every ordinance is [SAMPLE] in its title and number.
 */

return [

// ═══════════════════════════════════════════════════════════════════════
// ANNOUNCEMENTS
// ═══════════════════════════════════════════════════════════════════════
'announcements' => [

    [
        'slug'     => 'el-nino-magtipid-ng-tubig-2026',
        'cover'    => 'el-nino-water.png',
        'category' => 'safety',
        'urgency'  => 'important',
        'days_ago' => 1,
        'title' => [
            'fil' => 'El Niño: Magtipid ng Tubig at Mag-ipon ng Tubig-Ulan',
            'en'  => 'El Niño: Save Water and Collect Rainwater',
            'mn'  => 'El Niño: Pagdaginot sa Tubig ug Pagtigom sa Tubig sa Ulan',
        ],
        'body' => [
            'fil' => '<p>Ayon sa PAGASA, may malakas na El Niño na ngayon sa Karagatang Pasipiko na maaaring lumakas pa hanggang Disyembre 2026 at tumagal hanggang unang kalahati ng 2027. Sinabi ng DOST noong Oktubre 5 na maaaring makaranas ng tagtuyot ang lahat ng lalawigan sa bansa pagsapit ng Marso 2027.</p>'
                   . '<h3>Ano ang magagawa ng bawat pamilya</h3>'
                   . '<ul><li>Ayusin agad ang tumutulong gripo at tubo.</li>'
                   . '<li>Mag-ipon ng tubig-ulan sa malinis at may takip na lalagyan habang umuulan pa.</li>'
                   . '<li>Gamitin muli ang pinaghugasan ng bigas at gulay sa pagdidilig ng halaman.</li>'
                   . '<li>Huwag mag-imbak ng sobrang nakaboteng tubig. Ayon sa Malacañang, nagdudulot ito ng artipisyal na kakulangan at pagtaas ng presyo.</li></ul>'
                   . '<p>Takpan ang lahat ng imbakan ng tubig para hindi pamugaran ng lamok na may dengue. Para sa mga tanong, pumunta sa Barangay Hall.</p>',
            'en'  => '<p>According to PAGASA, a strong El Niño is now active in the Pacific Ocean and may strengthen further until December 2026, lasting into the first half of 2027. On October 5, the DOST said that every province in the country may experience drought by March 2027.</p>'
                   . '<h3>What every family can do</h3>'
                   . '<ul><li>Fix leaking faucets and pipes right away.</li>'
                   . '<li>Collect rainwater in clean, covered containers while it still rains.</li>'
                   . '<li>Reuse water from washing rice and vegetables to water your plants.</li>'
                   . '<li>Do not stockpile bottled water. According to Malacañang, hoarding creates artificial shortages and pushes prices up.</li></ul>'
                   . '<p>Cover every water container so dengue mosquitoes cannot breed in it. For questions, visit the Barangay Hall.</p>',
            'mn'  => '<p>Sumala sa PAGASA, aduna nay kusog nga El Niño karon sa Dagat Pasipiko nga mahimong mokusog pa hangtod Disyembre 2026 ug molungtad hangtod sa unang katunga sa 2027. Miingon ang DOST niadtong Oktubre 5 nga ang tanang lalawigan sa nasod mahimong makasinati og hulaw sa Marso 2027.</p>'
                   . '<h3>Unsay mahimo sa matag pamilya</h3>'
                   . '<ul><li>Ayoha dayon ang nagtagas nga gripo ug tubo.</li>'
                   . '<li>Tigoma ang tubig sa ulan sa limpyo ug tinabunan nga sudlanan samtang nag-ulan pa.</li>'
                   . '<li>Gamita pag-usab ang hinugasan sa bugas ug utanon sa pagbisibis sa mga tanom.</li>'
                   . '<li>Ayaw pagtigom og sobra nga tubig nga binotelya. Sumala sa Malacañang, kini makahatag og artipisyal nga kakulang ug pagsaka sa presyo.</li></ul>'
                   . '<p>Tabuni ang tanang sudlanan sa tubig aron dili pangitlogan sa lamok nga nagdala og dengue. Kung naa kay pangutana, adto sa Barangay Hall.</p>',
        ],
    ],

    [
        'slug'     => 'balita-halalang-barangay-sk-2026',
        'cover'    => 'bske-ballot.png',
        'category' => 'government',
        'urgency'  => 'important',
        'days_ago' => 3,
        'title' => [
            'fil' => 'Halalang Pambarangay at SK: Ang Kasalukuyang Kalagayan',
            'en'  => 'Barangay and SK Elections: Where Things Stand',
            'mn'  => 'Piliay sa Barangay ug SK: Ang Kahimtang Karon',
        ],
        'body' => [
            'fil' => '<p>Itinakda ng Batas Republika Blg. 12326 na gaganapin ang susunod na Halalang Pambarangay at Sangguniang Kabataan sa ikalawang Lunes ng Nobyembre 2028, sa halip na sa Nobyembre 2, 2026. Pinalawig din nito ang termino ng mga opisyal ng barangay at SK.</p>'
                   . '<p>May nakabinbing petisyon sa Korte Suprema na kumukuwestiyon sa batas na ito. Habang wala pang desisyon, itinigil muna ng COMELEC ang paghahanda para sa halalan ngayong taon.</p>'
                   . '<h3>Paalala</h3>'
                   . '<ul><li>Sundan lamang ang opisyal na anunsyo ng COMELEC at ng Korte Suprema.</li>'
                   . '<li>Huwag paniwalaan ang mga mensahe sa internet na walang malinaw na pinagmulan.</li>'
                   . '<li>Agad na ipapaalam ng barangay kapag may bagong desisyon.</li></ul>',
            'en'  => '<p>Republic Act No. 12326 moved the next Barangay and Sangguniang Kabataan Elections to the second Monday of November 2028, instead of November 2, 2026. The law also extended the term of barangay and SK officials.</p>'
                   . '<p>A petition questioning this law is pending before the Supreme Court. Until it rules, COMELEC has paused its preparations for an election this year.</p>'
                   . '<h3>Reminders</h3>'
                   . '<ul><li>Follow only the official announcements of COMELEC and the Supreme Court.</li>'
                   . '<li>Do not believe online messages that have no clear source.</li>'
                   . '<li>The barangay will inform residents as soon as there is a new decision.</li></ul>',
            'mn'  => '<p>Gitakda sa Balaod Republika Blg. 12326 nga ang sunod nga Piliay sa Barangay ug Sangguniang Kabataan himoon sa ikaduhang Lunes sa Nobyembre 2028, imbes sa Nobyembre 2, 2026. Gipalugway usab niini ang termino sa mga opisyal sa barangay ug SK.</p>'
                   . '<p>Aduna pay petisyon sa Korte Suprema nga nagkuwestiyon niini nga balaod. Samtang wala pay desisyon, gihunong sa COMELEC ang pagpangandam alang sa piliay karong tuiga.</p>'
                   . '<h3>Pahinumdom</h3>'
                   . '<ul><li>Sunda lamang ang opisyal nga pahibalo sa COMELEC ug sa Korte Suprema.</li>'
                   . '<li>Ayaw tuohi ang mga mensahe sa internet nga walay klaro nga gigikanan.</li>'
                   . '<li>Ipahibalo dayon sa barangay kung adunay bag-ong desisyon.</li></ul>',
        ],
    ],

    [
        'slug'     => 'undas-2026-walang-pasok-paalala',
        'cover'    => 'undas-candles.png',
        'category' => 'general',
        'urgency'  => 'normal',
        'days_ago' => 0,
        'title' => [
            'fil' => 'Undas 2026: Walang Pasok sa Nobyembre 1 at 2',
            'en'  => 'Undas 2026: November 1 and 2 Are Non-Working Days',
            'mn'  => 'Undas 2026: Walay Trabaho sa Nobyembre 1 ug 2',
        ],
        'body' => [
            'fil' => '<p>Sa bisa ng Proklamasyon Blg. 1006, serye ng 2025, espesyal na araw na walang pasok ang Nobyembre 1 (Araw ng mga Santo, Linggo) at Nobyembre 2 (Araw ng mga Kaluluwa, Lunes). Kaya may mahabang tatlong-araw na pahinga mula Oktubre 31 hanggang Nobyembre 2.</p>'
                   . '<h3>Paalala sa pagdalaw sa sementeryo</h3>'
                   . '<ul><li>Linisin at ayusin ang puntod bago mag-Oktubre 30.</li>'
                   . '<li>Bawal magdala ng matutulis na bagay, alak, at baraha o anumang sugal.</li>'
                   . '<li>Huwag iwanang nakasindi ang kandila nang walang nagbabantay.</li>'
                   . '<li>Bantayan ang mga bata at matatanda, at magdala ng tubig at payong.</li></ul>'
                   . '<p>Magkakaroon ng mesa ng tulong ng barangay sa may pasukan ng sementeryo mula Oktubre 31 hanggang Nobyembre 2.</p>',
            'en'  => '<p>Under Proclamation No. 1006, series of 2025, November 1 (All Saints\' Day, Sunday) and November 2 (All Souls\' Day, Monday) are special non-working days. This makes a three-day weekend from October 31 to November 2.</p>'
                   . '<h3>Reminders for cemetery visits</h3>'
                   . '<ul><li>Clean and repair family graves before October 30.</li>'
                   . '<li>Sharp objects, alcohol, playing cards and any form of gambling are not allowed.</li>'
                   . '<li>Never leave lit candles unattended.</li>'
                   . '<li>Keep an eye on children and the elderly, and bring water and an umbrella.</li></ul>'
                   . '<p>The barangay will run a help desk at the cemetery entrance from October 31 to November 2.</p>',
            'mn'  => '<p>Pinaagi sa Proklamasyon Blg. 1006, serye sa 2025, espesyal nga adlaw nga walay trabaho ang Nobyembre 1 (Adlaw sa mga Santos, Domingo) ug Nobyembre 2 (Adlaw sa mga Kalag, Lunes). Busa adunay taas nga tulo ka adlaw nga pahulay gikan Oktubre 31 hangtod Nobyembre 2.</p>'
                   . '<h3>Pahinumdom sa pagbisita sa sementeryo</h3>'
                   . '<ul><li>Limpyohi ug ayoha ang lubnganan una sa Oktubre 30.</li>'
                   . '<li>Gidili ang pagdala og hait nga butang, ilimnon nga makahubog, ug baraha o bisan unsang sugal.</li>'
                   . '<li>Ayaw biyai nga nagdilaab ang kandila nga walay nagbantay.</li>'
                   . '<li>Bantayi ang mga bata ug tigulang, ug pagdala og tubig ug payong.</li></ul>'
                   . '<p>Adunay lamesa sa tabang sa barangay sa may agianan sa sementeryo gikan Oktubre 31 hangtod Nobyembre 2.</p>',
        ],
    ],

    [
        'slug'     => 'buwan-ng-mga-katutubo-oktubre-2026',
        'cover'    => 'ip-month.png',
        'category' => 'social',
        'urgency'  => 'normal',
        'days_ago' => 7,
        'title' => [
            'fil' => 'Oktubre: Buwan ng mga Katutubong Mamamayan',
            'en'  => 'October Is National Indigenous Peoples Month',
            'mn'  => 'Oktubre: Bulan sa mga Lumad',
        ],
        'body' => [
            'fil' => '<p>Sa bisa ng Proklamasyon Blg. 1906, serye ng 2009, ang buwan ng Oktubre ay Pambansang Buwan ng mga Katutubong Mamamayan. Ipinagdiriwang natin ang kultura, wika at karapatan ng mga katutubo, kabilang ang ating mga kapatid na Manobo.</p>'
                   . '<p>Pinoprotektahan ng Batas Republika Blg. 8371 (Batas sa mga Karapatan ng mga Katutubong Mamamayan) ang kanilang lupang ninuno, kaugalian at sariling pamamahala.</p>'
                   . '<h3>Paano makikiisa</h3>'
                   . '<ul><li>Dumalo sa Pagdiriwang ng Kulturang Manobo sa Oktubre 24 sa plasa ng barangay.</li>'
                   . '<li>Gamitin ang wikang Manobo. Pindutin ang MN sa itaas ng BarangGabay para basahin ang mga abiso sa Manobo.</li>'
                   . '<li>Igalang ang mga tradisyon at sagradong lugar ng mga katutubo.</li></ul>',
            'en'  => '<p>Under Proclamation No. 1906, series of 2009, October is National Indigenous Peoples Month. We celebrate the culture, language and rights of indigenous peoples, including our Manobo brothers and sisters.</p>'
                   . '<p>Republic Act No. 8371 (the Indigenous Peoples\' Rights Act) protects their ancestral domains, customs and self-governance.</p>'
                   . '<h3>How to take part</h3>'
                   . '<ul><li>Join the Manobo Cultural Day on October 24 at the barangay plaza.</li>'
                   . '<li>Use the Manobo language. Press MN at the top of BarangGabay to read barangay notices in Manobo.</li>'
                   . '<li>Respect indigenous traditions and sacred places.</li></ul>',
            'mn'  => '<p>Pinaagi sa Proklamasyon Blg. 1906, serye sa 2009, ang bulan sa Oktubre mao ang Nasodnong Bulan sa mga Lumad. Gisaulog nato ang kultura, pinulongan ug katungod sa mga lumad, lakip na ang atong mga igsoon nga Manobo.</p>'
                   . '<p>Gipanalipdan sa Balaod Republika Blg. 8371 (Balaod sa mga Katungod sa mga Lumad) ang ilang yutang kabilin, batasan ug kaugalingong pagdumala.</p>'
                   . '<h3>Unsaon pag-apil</h3>'
                   . '<ul><li>Apil sa Pagsaulog sa Kultura sa Manobo sa Oktubre 24 sa plasa sa barangay.</li>'
                   . '<li>Gamita ang pinulongang Manobo. Pindota ang MN sa ibabaw sa BarangGabay aron mabasa ang mga pahibalo sa Manobo.</li>'
                   . '<li>Tahora ang mga tradisyon ug sagradong lugar sa mga lumad.</li></ul>',
        ],
    ],

    [
        'slug'     => 'ligtas-tigdas-bakuna-2026',
        'cover'    => 'measles-vaccine.png',
        'category' => 'health',
        'urgency'  => 'important',
        'days_ago' => 5,
        'title' => [
            'fil' => 'Ligtas Tigdas: Pabakunahan ang mga Batang 6 hanggang 59 na Buwan',
            'en'  => 'Measles-Rubella: Vaccinate Children Aged 6 to 59 Months',
            'mn'  => 'Ligtas Tigdas: Pabakunahi ang mga Bata nga 6 hangtod 59 ka Bulan',
        ],
        'body' => [
            'fil' => '<p>Sinimulan ng Kagawaran ng Kalusugan (DOH) sa Mindanao noong Enero 2026 ang kampanyang "Ligtas Tigdas" laban sa tigdas at rubella. Mula Enero hanggang Hunyo 2026, may 2,021 kaso ng tigdas at rubella sa buong bansa.</p>'
                   . '<p>Ayon sa DOH, ligtas at mabisa ang bakuna. Kung hindi pa nabakunahan ang inyong anak na 6 hanggang 59 na buwang gulang, dalhin siya sa Barangay Health Station. Libre ang bakuna.</p>'
                   . '<h3>Dalhin</h3>'
                   . '<ul><li>Talaan ng bakuna o "baby book" ng bata</li>'
                   . '<li>Sinumang magulang o tagapag-alaga na maaaring pumirma ng pahintulot</li></ul>'
                   . '<p>May pahabol na bakuna sa Oktubre 28. Tingnan ang Mga Kaganapan para sa oras.</p>',
            'en'  => '<p>The Department of Health (DOH) began its "Ligtas Tigdas" campaign against measles and rubella in Mindanao in January 2026. From January to June 2026, there were 2,021 measles and rubella cases nationwide.</p>'
                   . '<p>The DOH says the vaccine is safe and effective. If your child aged 6 to 59 months has not been vaccinated yet, bring them to the Barangay Health Station. The vaccine is free.</p>'
                   . '<h3>Bring</h3>'
                   . '<ul><li>The child\'s vaccination record or baby book</li>'
                   . '<li>A parent or guardian who can sign the consent form</li></ul>'
                   . '<p>A catch-up vaccination day is set for October 28. See Events for the schedule.</p>',
            'mn'  => '<p>Gisugdan sa Departamento sa Panglawas (DOH) sa Mindanao niadtong Enero 2026 ang kampanya nga "Ligtas Tigdas" batok sa tipdas ug rubella. Gikan Enero hangtod Hunyo 2026, adunay 2,021 ka kaso sa tipdas ug rubella sa tibuok nasod.</p>'
                   . '<p>Sumala sa DOH, luwas ug epektibo ang bakuna. Kung wala pa mabakunahi ang imong anak nga 6 hangtod 59 ka bulan ang edad, dad-a siya sa Barangay Health Station. Libre ang bakuna.</p>'
                   . '<h3>Dad-a</h3>'
                   . '<ul><li>Talaan sa bakuna o libro sa bata</li>'
                   . '<li>Ginikanan o tig-atiman nga makapirma sa pagtugot</li></ul>'
                   . '<p>Adunay dugang nga adlaw sa bakuna sa Oktubre 28. Tan-awa ang mga Kalihokan para sa oras.</p>',
        ],
    ],
],

// ═══════════════════════════════════════════════════════════════════════
// EVENTS
// ═══════════════════════════════════════════════════════════════════════
'events' => [

    [
        'slug'  => 'pagdiriwang-kulturang-manobo-2026',
        'cover' => 'manobo-cultural-day.png',
        'start' => '2026-10-24 08:00:00',
        'end'   => '2026-10-24 16:00:00',
        'venue' => 'Barangay Plaza, Bayogo',
        'lat'   => 9.2741, 'lng' => 125.9612,
        'title' => [
            'fil' => 'Pagdiriwang ng Kulturang Manobo',
            'en'  => 'Manobo Cultural Day Celebration',
            'mn'  => 'Pagsaulog sa Kultura sa Manobo',
        ],
        'body' => [
            'fil' => '<p>Bilang bahagi ng Buwan ng mga Katutubong Mamamayan, inaanyayahan ang lahat sa isang araw ng sayaw, musika, kuwentuhan at pagkaing Manobo.</p><ul><li>Umaga: pagbubukas at ritwal ng pagbasbas ng mga nakatatanda</li><li>Tanghali: salu-salo ng katutubong pagkain</li><li>Hapon: sayaw, tugtog ng agung at paligsahan sa pagkukuwento</li></ul><p>Libre ang pagdalo at bukas sa lahat ng edad.</p>',
            'en'  => '<p>As part of Indigenous Peoples Month, everyone is invited to a day of Manobo dance, music, storytelling and food.</p><ul><li>Morning: opening and a blessing ritual led by the elders</li><li>Noon: a shared meal of traditional food</li><li>Afternoon: dances, agung music and a storytelling contest</li></ul><p>Admission is free and open to all ages.</p>',
            'mn'  => '<p>Isip bahin sa Bulan sa mga Lumad, gidapit ang tanan sa usa ka adlaw sa sayaw, musika, sugilanon ug pagkaon sa Manobo.</p><ul><li>Buntag: pag-abli ug ritwal sa pagpanalangin sa mga tigulang</li><li>Udto: salo-salo sa lumad nga pagkaon</li><li>Hapon: sayaw, tugtog sa agung ug indigay sa pagsugilon</li></ul><p>Libre ang pagtambong ug abli sa tanang edad.</p>',
        ],
    ],

    [
        'slug'  => 'pagsasanay-pag-iipon-tubig-ulan-2026',
        'cover' => 'rainwater-workshop.png',
        'start' => '2026-10-21 09:00:00',
        'end'   => '2026-10-21 12:00:00',
        'venue' => 'Bayogo Barangay Hall',
        'lat'   => 9.2738, 'lng' => 125.9609,
        'title' => [
            'fil' => 'Libreng Pagsasanay: Pag-iipon ng Tubig-Ulan sa Bahay',
            'en'  => 'Free Workshop: Rainwater Harvesting at Home',
            'mn'  => 'Libre nga Pagbansay: Pagtigom sa Tubig sa Ulan sa Balay',
        ],
        'body' => [
            'fil' => '<p>Bilang paghahanda sa El Niño, matututunan ninyo kung paano gumawa ng simpleng imbakan ng tubig-ulan mula sa alulod ng bubong.</p><ul><li>Pagpili at paglilinis ng lalagyan</li><li>Paglalagay ng salaan at takip laban sa lamok</li><li>Ligtas na paggamit ng naipong tubig</li></ul><p>Magpalista sa Barangay Hall. Limitado sa 40 katao.</p>',
            'en'  => '<p>To prepare for El Niño, learn how to build a simple rainwater container fed from your roof gutter.</p><ul><li>Choosing and cleaning a container</li><li>Adding a filter and a mosquito-proof cover</li><li>Using stored water safely</li></ul><p>Register at the Barangay Hall. Limited to 40 participants.</p>',
            'mn'  => '<p>Isip pagpangandam sa El Niño, makat-on kamo unsaon paghimo og yano nga sudlanan sa tubig sa ulan gikan sa alulod sa atop.</p><ul><li>Pagpili ug paglimpyo sa sudlanan</li><li>Pagbutang og salaan ug tabon batok sa lamok</li><li>Luwas nga paggamit sa natigom nga tubig</li></ul><p>Pagpalista sa Barangay Hall. Limitado sa 40 ka tawo.</p>',
        ],
    ],

    [
        'slug'  => 'pahabol-na-bakuna-mga-bata-2026',
        'cover' => 'catch-up-vaccination.png',
        'start' => '2026-10-28 08:00:00',
        'end'   => '2026-10-28 15:00:00',
        'venue' => 'Bayogo Barangay Health Station',
        'lat'   => 9.2743, 'lng' => 125.9606,
        'title' => [
            'fil' => 'Pahabol na Bakuna para sa mga Bata',
            'en'  => 'Catch-Up Vaccination Day for Children',
            'mn'  => 'Dugang nga Adlaw sa Bakuna alang sa mga Bata',
        ],
        'body' => [
            'fil' => '<p>Libreng bakuna laban sa tigdas, rubella at iba pang sakit para sa mga batang hindi pa kumpleto ang bakuna.</p><ul><li>Para sa mga batang 6 hanggang 59 na buwang gulang</li><li>Dalhin ang talaan ng bakuna ng bata</li><li>Kasama ang magulang o tagapag-alaga</li></ul><p>Pinangungunahan ng mga Barangay Health Worker at ng kawani ng Rural Health Unit.</p>',
            'en'  => '<p>Free vaccines against measles, rubella and other diseases for children whose vaccinations are not yet complete.</p><ul><li>For children aged 6 to 59 months</li><li>Bring the child\'s vaccination record</li><li>A parent or guardian must come along</li></ul><p>Led by the Barangay Health Workers and Rural Health Unit staff.</p>',
            'mn'  => '<p>Libre nga bakuna batok sa tipdas, rubella ug uban pang sakit alang sa mga bata nga wala pa kompleto ang bakuna.</p><ul><li>Alang sa mga bata nga 6 hangtod 59 ka bulan ang edad</li><li>Dad-a ang talaan sa bakuna sa bata</li><li>Uban ang ginikanan o tig-atiman</li></ul><p>Pangulohan sa mga Barangay Health Worker ug kawani sa Rural Health Unit.</p>',
        ],
    ],

    [
        'slug'  => 'paglilinis-sementeryo-bago-undas-2026',
        'cover' => 'cemetery-cleanup.png',
        'start' => '2026-10-30 06:00:00',
        'end'   => '2026-10-30 11:00:00',
        'venue' => 'Bayogo Public Cemetery',
        'lat'   => 9.2752, 'lng' => 125.9621,
        'title' => [
            'fil' => 'Sama-samang Paglilinis ng Sementeryo Bago ang Undas',
            'en'  => 'Community Cemetery Clean-Up Before Undas',
            'mn'  => 'Sama-samang Paglimpyo sa Sementeryo Una sa Undas',
        ],
        'body' => [
            'fil' => '<p>Tulong-tulong nating linisin ang sementeryo bago dumating ang mga dadalaw sa Undas.</p><ul><li>Magdala ng walis, kalaykay at sako para sa basura</li><li>Paghiwalayin ang nabubulok at hindi nabubulok na basura</li><li>Bawal ang pagsusunog ng tuyong dahon at basura</li></ul><p>May libreng almusal para sa mga boluntaryo.</p>',
            'en'  => '<p>Let us work together to clean the cemetery before visitors arrive for Undas.</p><ul><li>Bring a broom, a rake and a sack for trash</li><li>Separate biodegradable and non-biodegradable waste</li><li>Burning dry leaves and trash is not allowed</li></ul><p>Free breakfast for volunteers.</p>',
            'mn'  => '<p>Magtinabangay kita sa paglimpyo sa sementeryo una moabot ang mga mobisita sa Undas.</p><ul><li>Pagdala og silhig, kalaykay ug sako para sa basura</li><li>Bulaga ang madunot ug dili madunot nga basura</li><li>Gidili ang pagsunog sa uga nga dahon ug basura</li></ul><p>Adunay libre nga pamahaw alang sa mga boluntaryo.</p>',
        ],
    ],

    [
        'slug'  => 'pagsasanay-paglikas-lindol-tsunami-2026',
        'cover' => 'evacuation-drill.png',
        'start' => '2026-11-07 09:00:00',
        'end'   => '2026-11-07 11:00:00',
        'venue' => 'Bayogo Elementary School',
        'lat'   => 9.2735, 'lng' => 125.9600,
        'title' => [
            'fil' => 'Pagsasanay sa Paglikas sa Lindol at Tsunami',
            'en'  => 'Earthquake and Tsunami Evacuation Drill',
            'mn'  => 'Pagbansay sa Pagbakwit sa Linog ug Tsunami',
        ],
        'body' => [
            'fil' => '<p>Bilang barangay sa tabing-dagat, kailangang alam ng bawat pamilya ang daan papunta sa mataas na lugar.</p><ul><li>Pagsasanay ng "yumuko, magtago at kumapit"</li><li>Paglakad sa ruta ng paglikas papunta sa mataas na lugar</li><li>Pagbibilang ng mga pamilya sa lugar na pagtitipunan</li></ul><p>Lahat ng purok ay hinihikayat na lumahok.</p>',
            'en'  => '<p>As a coastal barangay, every family needs to know the way to high ground.</p><ul><li>Practising "drop, cover and hold on"</li><li>Walking the evacuation route to high ground</li><li>Counting families at the assembly area</li></ul><p>Every purok is encouraged to take part.</p>',
            'mn'  => '<p>Isip barangay sa daplin sa dagat, kinahanglan kahibalo ang matag pamilya sa dalan padulong sa taas nga lugar.</p><ul><li>Pagbansay sa "yuko, tago ug kupot"</li><li>Paglakaw sa ruta sa pagbakwit padulong sa taas nga lugar</li><li>Pag-ihap sa mga pamilya sa lugar nga tigomanan</li></ul><p>Ang tanang purok gidasig nga moapil.</p>',
        ],
    ],
],

// ═══════════════════════════════════════════════════════════════════════
// ORDINANCES (plain-text descriptions; every one is [SAMPLE])
// ═══════════════════════════════════════════════════════════════════════
'ordinances' => [

    [
        'number'   => '[SAMPLE] 05-2026',
        'pdf'      => 'sample-ordinance-05-2026.pdf',
        'category' => 'Environmental',
        'enacted'  => '2026-10-05',
        'title' => [
            'fil' => '[SAMPLE] Kautusang Pambarangay Blg. 05-2026: Pagtitipid ng Tubig sa Panahon ng El Niño',
            'en'  => '[SAMPLE] Barangay Ordinance No. 05-2026: Water Conservation During El Niño',
            'mn'  => '[SAMPLE] Ordinansa sa Barangay Blg. 05-2026: Pagdaginot sa Tubig sa Panahon sa El Niño',
        ],
        'body' => [
            'fil' => "HALIMBAWANG ORDINANSA LAMANG, HINDI TUNAY.\n\nHabang may El Niño, bawal maghugas ng sasakyan gamit ang tuloy-tuloy na tubig mula sa hose, at bawal magdilig ng halaman mula 9:00 ng umaga hanggang 4:00 ng hapon. Dapat ayusin ng bawat sambahayan ang tumutulong tubo sa loob ng pitong araw mula nang mapansin ito. Hinihikayat ang pag-iipon ng tubig-ulan sa may takip na lalagyan.",
            'en'  => "SAMPLE ORDINANCE ONLY, NOT REAL.\n\nWhile El Niño lasts, washing vehicles with running water from a hose is prohibited, and watering plants is prohibited from 9:00 a.m. to 4:00 p.m. Every household must fix a leaking pipe within seven days of noticing it. Collecting rainwater in covered containers is encouraged.",
            'mn'  => "PANANGLITAN NGA ORDINANSA LAMANG, DILI TINUOD.\n\nSamtang adunay El Niño, gidili ang paghugas sa sakyanan gamit ang padayon nga tubig gikan sa hose, ug gidili ang pagbisibis sa tanom gikan 9:00 sa buntag hangtod 4:00 sa hapon. Kinahanglan ayohon sa matag panimalay ang nagtagas nga tubo sulod sa pito ka adlaw sukad kini mamatikdan. Gidasig ang pagtigom sa tubig sa ulan sa tinabunan nga sudlanan.",
        ],
    ],

    [
        'number'   => '[SAMPLE] 06-2026',
        'pdf'      => 'sample-ordinance-06-2026.pdf',
        'category' => 'Public Safety',
        'enacted'  => '2026-10-05',
        'title' => [
            'fil' => '[SAMPLE] Kautusang Pambarangay Blg. 06-2026: Pagbabawal sa Pagsusunog ng Basura',
            'en'  => '[SAMPLE] Barangay Ordinance No. 06-2026: Ban on Burning Trash',
            'mn'  => '[SAMPLE] Ordinansa sa Barangay Blg. 06-2026: Pagdili sa Pagsunog sa Basura',
        ],
        'body' => [
            'fil' => "HALIMBAWANG ORDINANSA LAMANG, HINDI TUNAY.\n\nAlinsunod sa Batas Republika Blg. 9003, bawal ang pagsusunog ng basura, tuyong dahon at plastik sa bukas na lugar. Mas mapanganib ito ngayong tag-init dahil sa El Niño, kung kailan madaling kumalat ang sunog. Dapat ihiwalay ang nabubulok at hindi nabubulok na basura at ilabas lamang sa araw ng pangongolekta.",
            'en'  => "SAMPLE ORDINANCE ONLY, NOT REAL.\n\nIn line with Republic Act No. 9003, burning trash, dry leaves and plastic in the open is prohibited. This is even more dangerous during the El Niño dry season, when fires spread easily. Biodegradable and non-biodegradable waste must be separated and put out only on collection day.",
            'mn'  => "PANANGLITAN NGA ORDINANSA LAMANG, DILI TINUOD.\n\nSumala sa Balaod Republika Blg. 9003, gidili ang pagsunog sa basura, uga nga dahon ug plastik sa abli nga lugar. Mas delikado kini karong ting-init tungod sa El Niño, kung kanus-a dali mokaylap ang sunog. Kinahanglan bulagon ang madunot ug dili madunot nga basura ug ipagawas lamang sa adlaw sa pagkolekta.",
        ],
    ],

    [
        'number'   => '[SAMPLE] 07-2026',
        'pdf'      => 'sample-ordinance-07-2026.pdf',
        'category' => 'Public Order',
        'enacted'  => '2026-10-08',
        'title' => [
            'fil' => '[SAMPLE] Kautusang Pambarangay Blg. 07-2026: Kaayusan sa Sementeryo Tuwing Undas',
            'en'  => '[SAMPLE] Barangay Ordinance No. 07-2026: Order in the Cemetery During Undas',
            'mn'  => '[SAMPLE] Ordinansa sa Barangay Blg. 07-2026: Kahusay sa Sementeryo Matag Undas',
        ],
        'body' => [
            'fil' => "HALIMBAWANG ORDINANSA LAMANG, HINDI TUNAY.\n\nMula Oktubre 31 hanggang Nobyembre 2, bawal sa loob ng sementeryo ang alak, sugal, matutulis na bagay at malakas na tugtugin. Bawal ang pagtitinda sa daanan. Dapat may nagbabantay sa bawat nakasinding kandila. Ang mga tanod ng barangay ang magpapatupad ng kautusang ito.",
            'en'  => "SAMPLE ORDINANCE ONLY, NOT REAL.\n\nFrom October 31 to November 2, alcohol, gambling, sharp objects and loud music are prohibited inside the cemetery. Selling goods in the walkways is prohibited. Every lit candle must be attended. Barangay tanods will enforce this ordinance.",
            'mn'  => "PANANGLITAN NGA ORDINANSA LAMANG, DILI TINUOD.\n\nGikan Oktubre 31 hangtod Nobyembre 2, gidili sulod sa sementeryo ang ilimnon nga makahubog, sugal, hait nga butang ug kusog nga tugtog. Gidili ang pagbaligya sa agianan. Kinahanglan adunay nagbantay sa matag nagdilaab nga kandila. Ang mga tanod sa barangay ang mopatuman niini nga ordinansa.",
        ],
    ],

    [
        'number'   => '[SAMPLE] 08-2026',
        'pdf'      => 'sample-ordinance-08-2026.pdf',
        'category' => 'Culture',
        'enacted'  => '2026-10-12',
        'title' => [
            'fil' => '[SAMPLE] Kautusang Pambarangay Blg. 08-2026: Paggamit ng Wikang Manobo sa mga Abiso ng Barangay',
            'en'  => '[SAMPLE] Barangay Ordinance No. 08-2026: Use of the Manobo Language in Barangay Notices',
            'mn'  => '[SAMPLE] Ordinansa sa Barangay Blg. 08-2026: Paggamit sa Pinulongang Manobo sa mga Pahibalo sa Barangay',
        ],
        'body' => [
            'fil' => "HALIMBAWANG ORDINANSA LAMANG, HINDI TUNAY.\n\nLahat ng abiso, babala at kautusan ng barangay ay dapat may bersiyon sa wikang Manobo, bukod sa Filipino at Ingles. Ang mga salitang wala pa sa diksiyonaryong Manobo ng barangay ay maaaring isulat sa Bisaya. Hinihikayat ang mga nakatatandang Manobo na tumulong sa pagdaragdag ng mga salita sa diksiyonaryo.",
            'en'  => "SAMPLE ORDINANCE ONLY, NOT REAL.\n\nEvery barangay notice, warning and ordinance must have a version in the Manobo language, in addition to Filipino and English. Words not yet in the barangay's Manobo dictionary may be written in Bisaya. Manobo elders are encouraged to help add words to the dictionary.",
            'mn'  => "PANANGLITAN NGA ORDINANSA LAMANG, DILI TINUOD.\n\nAng tanang pahibalo, pasidaan ug ordinansa sa barangay kinahanglan adunay bersiyon sa pinulongang Manobo, gawas sa Filipino ug Iningles. Ang mga pulong nga wala pa sa diksiyonaryo sa Manobo sa barangay mahimong isulat sa Bisaya. Gidasig ang mga tigulang nga Manobo nga motabang sa pagdugang og mga pulong sa diksiyonaryo.",
        ],
    ],
],

];
