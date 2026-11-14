<?php
//also run from the command line by bin/migrate.php, which can only upgrade an existing install
$cli = PHP_SAPI === 'cli';

ob_start();
if (!$cli) {
    session_save_path($_SERVER['DOCUMENT_ROOT'] . '/tmp');
    session_start();
}

require $_SERVER['DOCUMENT_ROOT'] . "/Functions/functions.php";
require $_SERVER['DOCUMENT_ROOT'] . "/Classes/classes.php";
require $_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php";

use Dotenv\Dotenv;

//bump this and add to $columns / $migrations below whenever the schema changes
$schema_version = OOTD_SCHEMA;

/**
 * @return bool true once the installer has run, installs from before 0.1.1 never set the "installed" setting
 * so an existing users table counts too
 */
function installed()
{
    if (users_check_table()) {
        return true;
    }

    return settings_check_table() && setting_get("installed") == 1;
}

//an existing install is never re-configured; an admin can re-run this to upgrade the database
$upgrading = false;
if ($cli && !(file_exists(__DIR__ . '/.env') && filesize(__DIR__ . '/.env') > 0)) {
    echo "No .env found. Install OOTD with bin/ootd install or through /install.php first.\n";
    exit(1);
}
if (file_exists(__DIR__ . '/.env') && filesize(__DIR__ . '/.env') > 0) {
    $dotenv = Dotenv::createMutable(__DIR__);
    $dotenv->load();

    try {
        $is_installed = installed();
    } catch (Throwable $e) {
        //can't reach the configured database, so don't let anyone replace the config
        $is_installed = true;
    }

    //bin/ootd install sets cli_install; bin/migrate.php only upgrades
    if ($cli && !$is_installed && empty($GLOBALS['cli_install'])) {
        echo "OOTD isn't installed in this database yet. Run bin/ootd install or use /install.php.\n";
        exit(1);
    }

    if ($is_installed) {
        //before schema 1 the admin permission was stored as 0
        $legacy_admin = !empty($_SESSION['user_id']) && current_permission() === 0 && (int)setting_get("schema_version", 0) < 1;
        if (!$cli && (empty($_SESSION['user_id']) || !(is_admin() || $legacy_admin))) {
            http_response_code(403);
            echo "OOTD is already installed. Log in as an admin to upgrade the database.";
            exit();
        }
        $upgrading = true;

        //installs from before 0.7.0 have no APP_URL, take it from the address the admin is using
        if ($cli && empty($_ENV['APP_URL'])) {
            if (empty($GLOBALS['cli_app_url'])) {
                echo "This install has no APP_URL yet. Run again with --app-url=https://your.site\n";
                exit(1);
            }
            $_SERVER['HTTP_HOST'] = parse_url($GLOBALS['cli_app_url'], PHP_URL_HOST) . (parse_url($GLOBALS['cli_app_url'], PHP_URL_PORT) ? ":" . parse_url($GLOBALS['cli_app_url'], PHP_URL_PORT) : "");
            $_SERVER['HTTPS'] = parse_url($GLOBALS['cli_app_url'], PHP_URL_SCHEME) == "https" ? "on" : "";
        }
        if (empty($_ENV['APP_URL'])) {
            file_put_contents(__DIR__ . '/.env', "\nAPP_URL=" . app_url() . "\n", FILE_APPEND);
            $_ENV['APP_URL'] = app_url();
            echo "<br>set APP_URL to " . escape($_ENV['APP_URL']);
        }
    }
}

//the command line installer has already written .env
if (!$upgrading && !$cli):

$_SESSION['installing'] = "started";

$env = fopen('.env', 'w');
fwrite($env, "");

$env_rules = array(
    "DB_HOST" => !empty($_POST['host']) ? $_POST['host'] : "localhost",
    "DB_USER" => !empty($_POST['user']) ? $_POST['user'] : "ootd",
    "DB_PASS" => !empty($_POST['password']) ? $_POST['password'] : str_split(id_gen(), 30)[1],
    "DB_SCHEMA" => !empty($_POST['schema']) ? $_POST['schema'] : "ootd",
    "PWD_SEED" => str_split(id_gen(), 30)[1],
    //self-hosted TinyMCE is GPL, swap for a commercial key if you have one
    "TINYMCE" => "gpl",
    //every public link and, later, every federated id is built from this, so it shouldn't change once set
    "APP_URL" => filter_var($_POST['app_url'] ?? "", FILTER_VALIDATE_URL) ? rtrim($_POST['app_url'], "/") : app_url()
);
$env_rules_string = "";

foreach ($env_rules as $env_key => $env_value) {
    $env_rules_string .= $env_key . "=" . $env_value . "\n";
}

fwrite($env, $env_rules_string);

fclose($env);

$dotenv = Dotenv::createMutable(__DIR__);
$dotenv->load();

endif;

//a fresh install is created at the current schema version, an older one is upgraded from 0
$fresh_install = !users_check_table();


$brands = array("A.P.C.", "0.8333333333", "181", "424", "711", ".., beaucoup", ".12 PUNTODODICI", "& Other Stories", "0-9", "032c", "04651/", "1-ONE", "10 Corso Como", "120% Lino", "14Bros", "16Arlington", "1725.a", "19.70 Nineteen Seventy", "1989 STUDIO", "19V69", "2-Biz", "21Fashion", "2W2M", "3.1 Phillip Lim", "3D Rose", "3Juin", "3x1", "40weft", "424 Fairfax", "44 Label Group", "4giveness", "4sdesigns", "4sold", "5preview", "7 For All Mankind", "A Bathing Ape", "A Gold E", "A|X Armani Exchange", "Absolute Cult", "Absolute Footwear", "Acne Studios", "adidas", "Áeron", "Ajvani", "Alaia", "Alanui", "Alberta Ferretti", "Alessandra Rich", "Alexander McQueen", "Alexander Wang", "Alice & Olivia", "Allegra K", "AllSaints", "Alpha Studio", "Altea", "Alysi", "AMBUSH", "Ami", "Amina Muaddi", "Amir Slama", "Amiri", "Ángel Alarcón", "Anine Bing", "Aniye By", "Antonelli", "Aquazzura", "Ariat", "Artery8", "Arty Pie", "Ash", "Asics", "Aspesi", "Aspinal of London", "Autry", "Axel Arigato", "BA&SH", "Baldinini", "Balenciaga", "Ballantyne", "Bally", "Balmain", "Barba", "Barbour", "Barena", "Barrow", "Beechfield", "Belstaff", "Berna", "Big Box Art", "Billionaire Boys Club", "Birkenstock", "Blanca Vita", "Blauer", "Blugirl", "Blumarine", "Bode", "Boglioli", "Bomboogie", "Bonamaison", "Bonateks", "BOSS", "Botanical Boys", "Bottega Veneta", "Brave Soul", "Brian Dales", "BRIGLIA 1949", "Brioni", "Brooks Brothers", "Brooksfield", "Brunello Cucinelli", "Bugatti", "Bulgari", "Burberry", "by FAR", "C.P. Company", "CafePress", "Calvin Klein", "Camper", "Canada Goose", "Canali", "Caprilite", "Carhartt Work in Progress", "Carolina Herrera", "Cartier", "Carvela", "Casablanca", "Casadei", "Castamere", "Celine", "Champion", "Chanel", "Chiara Ferragni", "Chloé", "Christian Louboutin", "Christophe Lemaire", "Churchs", "Circolo 1901", "Citizens Of Humanity", "Clarks", "Closed", "Cloud City 7", "Coach", "Coccinelle", "Colmar", "Columbia", "Comme Des Garçons", "Converse", "Coofandy", "Coperni", "Corneliani", "Courrèges", "CRZ YOGA", "Cupshe", "D.A.T.E.", "D.exterior", "Daniele Alessandrini", "Daniele Fiesoli", "Darkpark", "David Yurman", "Department Five", "Designers Guild", "Desigual", "Diadora", "Diamant L'éternel", "Diane Von Fürstenberg", "Dickies", "Diesel", "Dion Lee", "Dior", "Dirk Bikkembergs", "Disney", "Dita Eyewear", "DJNGN", "DKD Home Decor", "DKNY", "Dockers", "Dolce & Gabbana", "Dondup", "Dorothee Schumacher", "Doucal's", "Douceur d'Intérieur", "Dower & Hall", "DQT", "Dr. Martens", "Dream Pairs", "Dries Van Noten", "Drôle de Monsieur", "Drumohr", "Dsquared2", "Dune London", "Dunhill", "Dunlop", "È Mia", "East End Prints", "eBuy GB", "Ecco", "ECKHAUS LATTA", "Ecoalf", "Edwin", "Eglo", "Eleventy", "Elie Saab", "Elisabetta Franchi", "Ellesse", "Emanuele Bicocchi", "Emanuelle Vee", "Emilia Wickstead", "Emma & Gaia", "Emma Barclay", "Emporio Armani", "Enesco", "Entire studios", "Entre Amis", "Equipment", "Erdem", "Eres", "Erika Cavallini Semi Couture", "ERL", "Ermanno Scervino", "Ermenegildo Zegna", "Esprit", "Essentiel", "ESSEX GLAM", "Éterne", "Etro", "Études Studio", "European Culture", "Evans Lichfield", "Ever-pretty", "Extreme Cashmere", "Eye Catch", "Eyewear by David Beckham", "Eysa", "Eytys", "Fabiana Filippi", "Fabletics", "Faliero Sarti", "Falke", "Family Fir\$t Milano", "Farah", "Farm Rio", "Fat Face", "Fay", "Fear of God", "Fedeli", "Federica Tosi", "Fendi", "Feng Chen Wang", "Feoya", "Ferragamo", "Fila", "Filippa K", "FILIPPO DE LAURENTIIS", "Fimous", "Fine Art Prints", "Fisico", "FitFlop", "Fleur du Mal", "FLY London", "Fornasetti", "Forte_Forte", "Frame Company", "Frame Denim", "Frames by Post", "Frankie Morello", "Frankie Shop", "Fred Perry", "French Connection", "Fruit Of The Loom", "Furla", "furn.", "Furniture To Go", "FUTURO FASHION", "G-Star", "Gabor", "Gabriela Hearst", "Gaëlle Paris", "Galvan", "Ganni", "GANT", "Gardinia", "Gaudì", "Gauge81", "GCDS", "Generic", "Generico", "Genny", "Gentryportofino", "Geox", "Gestuz", "Gevril Group", "GFM", "Gheri", "Gia Borghini", "Giambattista Valli", "Gianni Chiarini", "Gianvito Rossi", "Gildan", "Gina Bacconi", "Giorgio Armani", "Girly HandBags", "Giuseppe Di Morabito", "Giuseppe Zanotti", "Givenchy", "GlamLondon", "Gola", "Golden Goose", "Grace Karin", "Gran Sasso", "Gucci", "Guess", "Guess Co", "Hackett", "Haculla", "Haikure", "Hanes", "Hanita", "Hanro", "Harlequin", "Harmont & Blaine", "Harris Wharf London", "Havaianas", "Haveone", "HAY", "Heat Holders", "Helikon-Tex", "Heliot Emil", "Helly Hansen", "Helmut Lang", "Henrik Vibskov", "Hereu", "Heritage", "Hermès", "Herno", "Heron Preston", "Hevò", "Hey Dude", "High", "Hinnominate", "HippoWarehouse", "Hisdern", "Hogan", "Högl", "Holibanna", "Homemania", "Homme Plissé Issey Miyake", "Hot Squash", "House Of Leather", "HUGO BOSS", "Hunter", "Hush Puppies", "IBB London", "Ibili", "iBlues", "Ibtom", "Iceberg", "Icecream", "Ichi", "Igi & Co", "Ih Nom Uh Nit", "IL BISONTE", "Ilse Jacobsen", "Imperial", "In Front", "Incotex", "Industville", "Infinity Leather", "Inov8", "InterDesign", "INUIKII", "Invicta", "Inwear", "Ipanema", "Irene Neuwirth", "Irish Crone", "Iro", "Irregular Choice", "Isa Boulder", "Isabel Benenato", "Isabel Marant", "Isabelle Blanche", "Isaia", "Islo Isabella Lorusso", "Isotoner", "Issey Miyake", "Italian Bed Linen", "Ivyline", "Ixos", "Izabel London", "Izzue", "J.Lindeberg", "J.W.Anderson", "Jack & Jones", "Jack Wolfskin", "Jacob Cohen", "Jacquemus", "James Lakeland", "James Perse", "Jana", "Janisramone", "Jean Paul Gaultier", "Jeansian", "Jeckerson", "Jeffrey Campbell", "Jennifer Behr", "Jenny Packham", "Jewelco London", "Jijil", "Jil Sander", "Jimmy Choo London", "JNBY", "Joe Browns", "Johanna Ortiz", "John Elliott + Co", "John Lewis", "John Richmond", "John Smedley", "Jolie Moi", "Joma", "Jomos", "Jools by Jenny Brown", "Josef Seibel", "Joseph", "JP1880", "Jucca", "Julius", "Junya Watanabe", "Just Cavalli", "Juun.J", "K-Swiss", "K-Way", "Kaffe", "Kangol", "KANGRA", "Kaos", "Kappa", "Karen Millen", "Karl Lagerfeld", "Karrimor", "Kate Spade New York", "Keen", "KEFITEVD", "Kenzo", "Khaite", "Khiry", "Khrisjoy", "Kickers", "Kiki De Montparnasse", "Kiko Kostadinov", "King & Priory", "Kiniki", "Kipling", "KitchenAid", "KitchenCraft", "Kiton", "Kleine Wolke", "Kocca", "Kollache", "Kolor", "Komar", "Konstsmide", "Kontatto", "Kosy Koala", "Koton", "Ksubi", "Kuboraum", "Küchenprofi", "Kurt Geiger", "L.k. Bennett", "L'autre Chose", "La DoubleJ", "La Martina", "La Petite Robe Di Chiara Boni", "Lacoste", "Laneus", "Lanvin", "Lardini", "Laura Ashley", "LE CREUSET", "Le Silla", "Leaf", "LeahWard", "Lee", "Levi's", "Liberty", "Lily & Roo", "Lina & Lily", "Linda Farrow", "Lisa Yang", "Liu Jo", "Liviana Conti", "Living and Home", "Loewe", "Lola Cruz", "Longchamp", "Loops", "Lorena Antoniazzi", "Loro Piana", "Louis Vuitton", "Loulou Studio", "Lounge Underwear", "Love Moschino", "low brand", "Lucide", "Lumartos", "Lush Décor", "Lyle & Scott", "Magda Butrym", "Maison Kitsuné", "Maison Margiela", "Maje", "Majestic Filatures", "Malo", "Manila Grace", "Manolo Blahnik", "Manuel Ritz", "Marc Jacobs", "Marcelo Burlon", "Marco Tozzi", "Marine Serre", "Marni", "Marsèll", "Mason's", "Mauro Grifoni", "Max Mara", "MC2 Saint Barth", "MCM", "Merrell", "Michael Coal", "Michael Kors", "Michael Michael Kors", "Miharayasuhiro", "Misbhv", "Missoni", "Miu Miu", "Moncler", "Monica Vinader", "Moorer", "Moose Knuckles", "Moschino", "Moss", "Mother", "Mou", "Mountain Warehouse", "Msgm", "MUGLER", "N.Peal", "N°21", "NA-KD", "Nanamíca", "Nanushka", "Napapijri", "Natasha Zinko", "Nautica", "Nauticalia", "Needles", "Neighborhood", "Neil Barrett", "Nemesis Now", "Nenette", "Nensi Dojaka", "Nero Giardini", "Netlighting", "New Balance", "New Era", "New Rock", "Nialaya", "Nicholas", "Nike", "Nike Jordan", "Nila & Nila", "Nili Lotan", "Nina Ricci", "Nine In The Morning", "Nine West", "Nira Rubens", "Nissa", "Nkuku", "Nn.07", "Nobody's Child", "Norma Kamali", "Norse Projects", "North Sails", "Nude", "Nudie Jeans", "O'Neill", "Oakley", "OAMC", "Obey", "Object", "Ocean Plus", "Odi Et Amo", "Off-white", "Officina 36", "Officine Creative", "Officine Générale", "Oliver Brown", "Oliver Peoples", "Omega", "On", "On Running", "One Teaspoon", "Onitsuka Tiger", "Only", "Only & Sons", "OOF", "Opening Ceremony", "Orciani", "Original Penguin", "Original Vintage Style", "Orlebar Brown", "Orolay", "Orphelia", "Osborne & Little", "Oscar De La Renta", "Oséree", "Osoi", "Osprey", "ottod'Ame", "Ottolinger", "Our Legacy", "OUTSUNNY", "Ovye By Cristina Lucchi", "Oxo", "P.A.R.O.S.H.", "Paco Rabanne", "Paige", "Pal Zileri", "Palm Angels", "Paloma Barceló", "Pantaloni Torino", "Paolo Pecora", "Parajumpers", "PARIS TEXAS", "Parosh", "Part Two", "Parts Of Four", "Patou", "Patrizia Pepe", "Paul & Shark", "Paul Smith", "Paulmann", "Pepe Jeans London", "Persol", "PESERICO", "Peuterey", "Philipp Plein", "Philippe Model", "Philosophy di Lorenzo Serafini", "Pieces", "Pinko", "Pleats Please Issey Miyake", "Plein Sport", "Pollini", "Polo Ralph Lauren", "Prada", "Premiata", "Premier", "Premier Housewares", "Proenza Schouler", "Pucci", "Puma", "Purple", "Q/S designed by", "Q1", "Q36.5", "Qasimi", "QB24", "QEEBOO", "QIYUN.Z", "Ql2 Quelledue", "QS by s.Oliver", "Quadra", "Quail", "Quail Ceramics", "Qualy", "Quantum Courage", "QUATTRO.DECIMI", "Quay", "Quayside", "Qué Rico", "Queen", "Queen Helena", "Queen Kerosin", "Queens Of The Stone Age", "Queensway", "QueGuapa", "Quenchy London", "Quest", "Questo Casa", "Quid", "Quiksilver", "QUINTO EGO", "Quinton & Chadwick", "QUINTRA", "Quira", "Quiva", "Quodlibet", "Quoizel", "Quotrell", "Quttin", "QWUVEDS", "R13", "Radley London", "Raf Simons", "Rag & Bone", "Rains", "Ralph Lauren", "Ray-Ban", "Re-hash", "Re/Done", "Rebecca Vallance", "Red Valentino", "Red(v)", "Reebok", "Regatta", "Reiss", "Relaxdays", "Remain", "Rene Caovilla", "Replay", "Represent", "Retro Superfuture", "Retrofête", "Rhude", "Rick Owens", "Rieker", "Riva Paoletti", "Roberto Cavalli", "Roberto Collina", "Roberto Festa Milano", "Roberto Ricci Design", "Rocket Dog", "Roger Vivier", "Róhe", "Roman Originals", "Rossignol", "Rossopuro", "Rotate", "Roy Rogers", "Russell & Bromley", "s.Oliver", "sacai", "Saint Laurent", "Saint Laurent Eyewear", "Salomon", "Samsøe & Samsøe", "Sanderson", "Sandro", "Santoni", "Saucony", "Savage x Fenty", "Save The Duck", "Scarosso", "See By Chloé", "Selected", "Self Portrait", "Semicouture", "Sergio Rossi", "Show all brands with A »", "Show all brands with B »", "Show all brands with C »", "Show all brands with D »", "Show all brands with E »", "Show all brands with F »", "Show all brands with G »", "Show all brands with H »", "Show all brands with I »", "Show all brands with J »", "Show all brands with K »", "Show all brands with L »", "Show all brands with M »", "Show all brands with N »", "Show all brands with O »", "Show all brands with P »", "Show all brands with Q »", "Show all brands with R »", "Show all brands with S »", "Show all brands with T »", "Show all brands with U »", "Show all brands with V »", "Show all brands with W »", "Show all brands with Y »", "Show all brands with Z »", "SIMKHAI", "Simona Corsellini", "Simone Rocha", "Skechers", "Soleil d'Ocre", "Sportmax", "Sporty & Rich", "Stand Studio", "Staud", "Stella McCartney", "Steve Madden", "Stone Island", "Stuart Weitzman", "STUDIO NICHOLSON", "Stuff4", "styleBREAKER", "Suicoke", "Sun 68", "Superdry", "SUPREME", "Swarovski", "Tagliatore", "Tamaris", "Ted Baker", "Tempur", "Ten c", "Tendycoco", "Teva", "The Attico", "The North Face", "The Row", "Themoirè", "Theory", "Thom Browne", "Thom Krom", "Tiffany & Co.", "Timberland", "Tintoria Mattei", "TK Maxx", "Tod's", "Toga Archives", "Tokyo Laundry", "Tom Ford", "Tom Ford Eyewear", "Tom Tailor", "Tommy Hilfiger", "Tommy Jeans", "Toms", "Tory Burch", "Toteme", "Tower", "Toy G", "Trendyol", "Trespass", "TruClothing", "True Religion", "True Royal", "Trussardi", "Twin-Set", "Twinset", "U.P.W.W.", "U.S.Polo Association", "UGG", "UGP Campus Apparel", "Ulla Johnson", "Ulla Popken", "Uma", "Uma Wang", "Umbra", "Umbro", "Un-Namable", "UNCOMMON MATTERS", "Under Armour", "Under the Rose", "Undercover", "Undercover by Jun Takahashi", "Uneek clothing", "Uniform Bridge", "Unionbay", "Unisa", "United Nude", "Unity", "Universal Textiles", "Universal Works", "UNLACE", "Unlisted", "UNOde50", "Unravel", "Unreal Fur", "Up To Be", "Urban Classics", "Urban Code", "Urban Jacks", "Urban Nature Culture", "Urban Road", "Usa Pro", "Utopia Tableware", "Uvex", "UYN", "V° 73", "Valentino", "Valentino Garavani", "Valextra", "Valiclud", "Vanessa Scott", "Vans", "Varley", "Veja", "Velvet", "Vera Bradley", "Verdissima", "Vero Moda", "Veronica Beard", "Versace", "Versace Jeans Couture", "VETEMENTS", "Via Roma 15", "Vibram Fivefingers", "Vic Matié", "Vicolo", "Victoria Beckham", "Viktor & Rolf", "Vila", "Vilebrequin", "Villa Nova", "Villeroy & Boch", "Vince", "Vionic", "Vision Of Super", "Vispring", "Visvim", "Vivetta", "Vivienne Westwood", "Vivis", "Vogue", "Voile Blanche", "Volcom", "VTMNTS", "W6YZ", "Wales Bonner", "Walker and Hawkes", "Wall Smart Designs", "Walter Van Beirendonck", "Walther Design", "Wandler", "Wantdo", "Wardrobe.NYC", "Wash+Dry", "Wax Lyrical", "We11done", "WearAll", "Wee Blue Coo", "Weekend by Max Mara", "Weili Zheng", "Weird Fish", "Welcome Furniture", "Wellcoda", "WENKO", "Westford Mill", "Whistles", "White Mountain", "White Sand", "White Stuff", "White Wise", "Who Decides War by Ev Bravado", "Wild Cashmere", "WM Bartleet & Sons 1750", "Wolford", "Wood Wood", "Woolrich", "Wooyoungmi", "World of Shawls", "Wouters & Hendrix", "Wrangler", "Wtaps", "Wüsthof", "Wylder", "X Bionic", "X Loop", "X Rocker", "X-Cape", "Xacus", "Xagon Man", "Xander Zhou", "Xandres", "Xappeal", "XAVAX", "XBRAND", "xc", "XCNGG", "XConcept", "XD Design", "Xero Shoes", "XFentech", "Xian", "Ximonlee", "Xirena", "XLARGE", "XLBoom", "XLC", "XN PERENNE", "Xôcoi", "xoxo", "XOXOGOODBOY", "XP", "XPLCT Studios", "Xplicit", "Xpooos", "Xs Milano", "Xscape", "XT Studio", "Xti", "Xtratuf", "Xu Zhi", "Xue", "Y / Project", "Y-E-S", "Yali Glass", "Yamazaki Tableware", "Yan Simmon", "Yankee Candle Company", "Yatay", "YaYa", "Yeezy by Kanye West", "Yeprem", "Yes London", "Yes-Zee", "YESET", "Ymc You Must Create", "Yogalicious", "Yogi", "Yohji Yamamoto", "Yoko London", "Yonger & Bresson", "Yonglan", "Yonique", "Yoon", "YORK street", "Yoshiokubo", "Young Poets Society", "Yours Clothing", "Youth", "Youths in Balaclava", "Yu Mei", "Yuhan Wang", "Yume Yume", "Yumi", "Yupoong", "Yuzefi", "Yves Delorme", "Yves Salomon", "Yvonne Léon", "Yvonne Sporre", "YYW", "Zadig&Voltaire", "Zafferano", "Zaful", "Zamberlan", "Zanat", "Zanella", "Zanellato", "Zanone", "Zanzea", "Zeagoo", "Zeal", "Zedzzz", "ZEE FASHION", "ZELLER", "Zenker", "ZEP", "Zero Construction", "Zespà", "Zeynep Arcay", "Zhelda", "Zhrill", "ZhuiKun", "Ziggy Chen", "Zilverstad", "Zimmermann", "Zinda", "ZLYC", "Zo!Home", "Zoe", "Zoe & Morgan", "Zoë Chicco", "Zoeppritz", "Zoggs", "Zoku", "Zone Denmark", "Zuhair Murad", "Zuny", "Zwilling", "Zyliss");

$categories = array(
    "Tops" => array(
        "level" => 0,
        "in_moderation" => 0
    ),
    "Bottoms" => array(
        "level" => 0,
        "in_moderation" => 0
    ),
    "Dresses" => array(
        "level" => 0,
        "in_moderation" => 0
    ),
    "Activewear" => array(
        "level" => 0,
        "in_moderation" => 0
    ),
    "Swimwear" => array(
        "level" => 0,
        "in_moderation" => 0
    ),
    "Outerwear" => array(
        "level" => 0,
        "in_moderation" => 0
    ),
    "Jumper" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Cardigan" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Coat" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Jacket" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Blazer" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Blouse" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Co-ord" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Hoodie" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Sweatshirt" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Jumpsuit" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Playsuit" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Shirt" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Shorts" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Suit" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "T-Shirt" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Vest" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Camisole" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Skirt" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Jeans" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Trousers" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Chinos" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Joggers" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Polo shirt" => array(
        "level" => 1,
        "in_moderation" => 0
    ),
    "Tracksuit" => array(
        "level" => 1,
        "in_moderation" => 0
    )
);

$settings = array(
    "installed" => array(
        "type" => "bool",
        "value" => 0
    ),
    "schema_version" => array(
        "type" => "int",
        "value" => $fresh_install ? $schema_version : 0
    ),
    "recompile_sass" => array(
        "type" => "bool",
        "value" => 1
    ),
    //0.10.0: instance settings, shown on /about and used across the site
    "instance_name" => array(
        "type" => "string",
        "value" => "OOTD"
    ),
    "instance_description" => array(
        "type" => "text",
        "value" => "Outfit of the day: a wardrobe, a daily outfit and the people whose style you follow."
    ),
    "instance_rules" => array(
        "type" => "text",
        "value" => ""
    ),
    "admin_contact" => array(
        "type" => "string",
        "value" => ""
    ),
    "registration" => array(
        "type" => "choice:closed|invite|open",
        "value" => "invite"
    ),
    "default_visibility" => array(
        "type" => "choice:public|members|followers|private",
        "value" => "members"
    )
);

//order matters, ids are used as users.permission (see Functions/permission.php)
$permissions = array("admin", "moderator", "user");

//columns added since the first release, added to existing tables when missing
$columns = array(
    "users" => array(
        //CSRF tokens are stored against the user since 0.2.0; databases from before the column existed lack it
        "csrf" => "varchar(256) NULL"
    ),
    "garments" => array(
        "garment_id" => "varchar(128) NULL AFTER `garment_num`",
        "user_id" => "int(11) NULL AFTER `garment_id`",
        "brand" => "int(11) NULL",
        "category" => "int(11) NULL",
        "timestamp" => "timestamp DEFAULT CURRENT_TIMESTAMP"
    ),
    "outfits" => array(
        "timestamp" => "timestamp DEFAULT CURRENT_TIMESTAMP"
    ),
    "blogs" => array(
        "tags" => "varchar(512) NULL"
    )
);
//0.9.0: accounts can ask to approve new followers
$columns["users"]["approve_followers"] = "tinyint(1) NOT NULL DEFAULT 0";
//0.8.0: photos live in the media table
foreach (array("users", "garments", "outfits") as $media_table) {
    $columns[$media_table]["media_id"] = "varchar(64) NULL";
}
//0.7.0: who can see each post, plus edit and soft delete
foreach (array("garments", "outfits", "blogs") as $post_table) {
    $columns[$post_table]["visibility"] = "varchar(16) NOT NULL DEFAULT 'members'";
    $columns[$post_table]["updated_at"] = "timestamp NULL DEFAULT NULL";
    $columns[$post_table]["deleted_at"] = "timestamp NULL DEFAULT NULL";
}

//one-off data changes, run once each when upgrading past that version
$migrations = array(
    1 => array(
        //permissions used to be 0 = admin, 1 = everyone else
        "UPDATE `users` SET `permission` = CASE WHEN `permission` = 0 THEN 1 WHEN `permission` = 1 THEN 3 ELSE `permission` END",
        "ALTER TABLE `users` ALTER COLUMN `permission` SET DEFAULT 3",
        "ALTER TABLE `garments` MODIFY `image` mediumblob NULL",
        "ALTER TABLE `outfits` MODIFY `image` mediumblob NULL"
    ),
    2 => array(
        //session ids are checked against this on every request since 0.4.0
        "ALTER TABLE `users` MODIFY `session` varchar(128) NULL"
    ),
    3 => array(
        //the connection asked for utf8mb4 incorrectly before 0.7.0 (AUDIT F16), make sure every table stores it
        //no COLLATE: utf8mb4_0900_ai_ci is MySQL 8 only, MariaDB picks its own default
        "ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4",
        "ALTER TABLE `garments` CONVERT TO CHARACTER SET utf8mb4",
        "ALTER TABLE `outfits` CONVERT TO CHARACTER SET utf8mb4",
        "ALTER TABLE `blogs` CONVERT TO CHARACTER SET utf8mb4",
        "ALTER TABLE `brands` CONVERT TO CHARACTER SET utf8mb4",
        "ALTER TABLE `categories` CONVERT TO CHARACTER SET utf8mb4",
        "ALTER TABLE `invites` CONVERT TO CHARACTER SET utf8mb4",
        "ALTER TABLE `wears` CONVERT TO CHARACTER SET utf8mb4",
        "ALTER TABLE `settings` CONVERT TO CHARACTER SET utf8mb4",
        "ALTER TABLE `permissions` CONVERT TO CHARACTER SET utf8mb4"
    ),
    4 => array(
        //older MariaDB/MySQL tables got ON UPDATE CURRENT_TIMESTAMP added to their first timestamp column,
        //so editing a post (0.7.0) would move its date; an explicit DEFAULT without ON UPDATE removes that
        "ALTER TABLE `garments` MODIFY `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE `outfits` MODIFY `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE `blogs` MODIFY `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE `invites` MODIFY `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE `wears` MODIFY `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP",
        //early blogs tables capped content at 4096 characters
        "ALTER TABLE `blogs` MODIFY `title` varchar(256) NULL, MODIFY `excerpt` varchar(1024) NULL, MODIFY `content` text NULL"
    ),
    5 => array(
        //images move out of BLOB columns, see media_migrate_blobs()
        "media_migrate_blobs"
    ),
    //follows, notifications and jobs are new tables, created above
    6 => array(),
    //password_resets is a new table; the old image BLOBs were copied to the media store in 5 (D11)
    7 => array(
        "media_drop_blobs"
    )
);

function brands_check_table()
{
    $sql = "SHOW TABLES LIKE 'brands';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (brands)'] ?? null) == "brands") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function brands_create_table()
{
    $sql = "CREATE TABLE brands(
        brand_id INT AUTO_INCREMENT, 
        brand_name VARCHAR(100) NOT NULL,
        PRIMARY KEY(brand_id)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return brands_check_table();
}

function brands_count_rows()
{
    $sql = "SELECT COUNT(*) FROM brands;";
    $db = new Mysql();
    $row = $db->Fetch($sql, null);

    $count = (array)$row[0];

    return $count['COUNT(*)'];
}

function brands_import($brands)
{
    $sql = "INSERT INTO brands(brand_name) VALUES";

    foreach ($brands as $key => $brand) {
        $sql .= "(\"" . $brand . "\")";
        if ($key < count($brands) - 1) {
            $sql .= ", ";
        }
    }

    //echo $sql;

    $db = new Mysql();
    $db->Fetch($sql, null);

    if (count($brands) == brands_count_rows()) {
        return true;
    } else {
        throw new Exception("brand import unsuccesful " . count($brands) . " / " . brands_count_rows());
    }

}

function categories_check_table()
{
    $sql = "SHOW TABLES LIKE 'categories';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (categories)'] ?? null) == "categories") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function categories_create_table()
{
    $sql = "CREATE TABLE categories(
        category_id INT AUTO_INCREMENT, 
        categories VARCHAR(100) NOT NULL,
        level INT NOT NULL,
        in_moderation TINYINT(1) NOT NULL,
        PRIMARY KEY(category_id)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return categories_check_table();
}

function categories_count_rows()
{
    $sql = "SELECT COUNT(*) FROM categories;";
    $db = new Mysql();
    $row = $db->Fetch($sql, null);

    $count = (array)$row[0];

    return $count['COUNT(*)'];
}

function categories_import($categories)
{
    $sql = "INSERT INTO categories(categories, level, in_moderation) VALUES";

    $count = 0;
    foreach ($categories as $category => $options) {
        $sql .= "(\"";
        $sql .= $category . "\",";
        $sql .= $options['level'] . ",";
        $sql .= $options['in_moderation'] . ")";
        if ($count < count($categories) - 1) {
            $sql .= ", ";
        }
        $count++;
    }

    $db = new Mysql();
    $db->Fetch($sql, null);

    if (count($categories) == categories_count_rows()) {
        return true;
    } else {
        throw new Exception("categories import unsuccesful " . count($categories) . " / " . categories_count_rows());
    }

}

function settings_check_table()
{
    $sql = "SHOW TABLES LIKE 'settings';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (settings)'] ?? null) == "settings") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function settings_create_table()
{
    $sql = "CREATE TABLE settings(
        setting_num INT AUTO_INCREMENT, 
        setting VARCHAR(100) NOT NULL,
        type VARCHAR(100) NOT NULL,
        value VARCHAR(100) NOT NULL,
        PRIMARY KEY(setting_num)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return settings_check_table();
}

function settings_count_rows()
{
    $sql = "SELECT COUNT(*) FROM settings;";
    $db = new Mysql();
    $row = $db->Fetch($sql, null);

    $count = (array)$row[0];

    return $count['COUNT(*)'];
}

function settings_import($settings)
{
    if (count($settings) == 0) {
        return true;
    }

    $sql = "INSERT INTO settings(setting, type, value) VALUES";

    $count = 0;
    foreach ($settings as $setting => $options) {
        $sql .= "(\"";
        $sql .= $setting . "\",";
        $sql .= "\"" . $options['type'] . "\",";
        $sql .= "\"" . $options['value'] . "\")";
        if ($count < count($settings) - 1) {
            $sql .= ", ";
        }
        $count++;
    }

    $db = new Mysql();
    $db->Fetch($sql, null);

    return true;

}

function settings_missing($settings)
{
    $missing = array();
    foreach ($settings as $setting => $options) {
        if (setting_get($setting) === null) {
            $missing[$setting] = $options;
        }
    }
    return $missing;
}

function permissions_check_table()
{
    $sql = "SHOW TABLES LIKE 'permissions';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (permissions)'] ?? null) == "permissions") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function permissions_create_table()
{
    $sql = "CREATE TABLE permissions(
        permission_id INT AUTO_INCREMENT, 
        permission VARCHAR(100) NOT NULL,
        PRIMARY KEY(permission_id)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return permissions_check_table();
}

function permissions_count_rows()
{
    $sql = "SELECT COUNT(*) FROM permissions;";
    $db = new Mysql();
    $row = $db->Fetch($sql, null);

    $count = (array)$row[0];

    return $count['COUNT(*)'];
}

function permissions_import($permissions)
{
    $sql = "INSERT INTO permissions(permission) VALUES";

    foreach ($permissions as $key => $permission) {
        $sql .= "(\"" . $permission . "\")";
        if ($key < count($permissions) - 1) {
            $sql .= ", ";
        }
    }

    $db = new Mysql();
    $db->Fetch($sql, null);

    if (count($permissions) == permissions_count_rows()) {
        return true;
    } else {
        throw new Exception("permission import unsuccesful " . count($permissions) . " / " . permissions_count_rows());
    }

}

function users_check_table()
{
    $sql = "SHOW TABLES LIKE 'users';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (users)'] ?? null) == "users") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function users_create_table()
{
    $sql = "CREATE TABLE users(
        user_id	int(11) AUTO_INCREMENT,
        username varchar(32),
        email varchar(128),
        password varchar(256),
        first_name varchar(64),
        last_name varchar(64),
        permission int(11) DEFAULT 3,
        session	varchar(128) NULL,
        csrf varchar(256),
        PRIMARY KEY(user_id)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return users_check_table();
}

function garments_check_table()
{
    $sql = "SHOW TABLES LIKE 'garments';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (garments)'] ?? null) == "garments") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function garments_create_table()
{
    $sql = "CREATE TABLE garments(
        garment_num	int(11) AUTO_INCREMENT,
        garment_id varchar(128) NULL,
        user_id int(11) NULL,
        garment_name varchar(128) NULL,
        description varchar(1024) NULL,
        tags varchar(512) NULL,
        brand int(11) NULL,
        category int(11) NULL,
        timestamp timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(garment_num)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return garments_check_table();
}


function outfits_check_table()
{
    $sql = "SHOW TABLES LIKE 'outfits';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (outfits)'] ?? null) == "outfits") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function outfits_create_table()
{
    $sql = "CREATE TABLE outfits(
        outfit_num	int(11) AUTO_INCREMENT,
        outfit_id varchar(128) NULL,
        user int(11),
        garments varchar(1024) NULL,
        description varchar(1024) NULL,
        tags varchar(512) NULL,
        timestamp timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(outfit_num)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return outfits_check_table();
}

function blogs_check_table()
{
    $sql = "SHOW TABLES LIKE 'blogs';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (blogs)'] ?? null) == "blogs") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function blogs_create_table()
{
    $sql = "CREATE TABLE blogs(
        blog_num int(11) AUTO_INCREMENT,
        blog_id varchar(128) NULL,
        user int(11),
        title varchar(256) NULL,
        excerpt varchar(1024) NULL,
        content text NULL,
        outfits varchar(1024) DEFAULT '[]',
        garments varchar(1024) DEFAULT '[]',
        tags varchar(512) NULL,
        timestamp timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(blog_num)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return blogs_check_table();
}

function invites_check_table()
{
    $sql = "SHOW TABLES LIKE 'invites';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (invites)'] ?? null) == "invites") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function invites_create_table()
{
    $sql = "CREATE TABLE invites(
        invite_num int(11) AUTO_INCREMENT,
        email varchar(128) NOT NULL,
        code varchar(128) NULL,
        state varchar(16) NOT NULL DEFAULT 'requested',
        used_by int(11) NULL,
        timestamp timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(invite_num)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return invites_check_table();
}

function wears_check_table()
{
    $sql = "SHOW TABLES LIKE 'wears';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (wears)'] ?? null) == "wears") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function wears_create_table()
{
    $sql = "CREATE TABLE wears(
        wear_num int(11) AUTO_INCREMENT,
        user int(11) NOT NULL,
        outfit_num int(11) NULL,
        garment_num int(11) NULL,
        worn_on date NOT NULL,
        timestamp timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(wear_num),
        KEY user_worn_on (user, worn_on),
        KEY garment_num (garment_num)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return wears_check_table();
}

function media_check_table()
{
    $sql = "SHOW TABLES LIKE 'media';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (media)'] ?? null) == "media") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function media_create_table()
{
    $sql = "CREATE TABLE media(
        media_num int(11) AUTO_INCREMENT,
        media_id varchar(64) NOT NULL,
        user_id int(11) NOT NULL,
        mime varchar(32) NOT NULL,
        extension varchar(8) NOT NULL,
        width int(11) NULL,
        height int(11) NULL,
        bytes int(11) NULL,
        alt_text varchar(1500) NULL,
        remote_url varchar(2048) NULL,
        timestamp timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at timestamp NULL DEFAULT NULL,
        PRIMARY KEY(media_num),
        UNIQUE KEY media_id (media_id)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return media_check_table();
}

/**
 * schema 5: move every image out of its BLOB column into the media store. The BLOB columns are left
 * in place (unused) until a later release drops them, so a rollback to 0.7.x still has its images.
 */
function media_migrate_blobs()
{
    $tables = array(
        "users" => array("key" => "user_id", "owner" => "user_id"),
        "garments" => array("key" => "garment_num", "owner" => "user_id"),
        "outfits" => array("key" => "outfit_num", "owner" => "user")
    );
    foreach ($tables as $table => $columns) {
        if (!column_exists($table, "image")) {
            continue;
        }
        $db = new Mysql();
        $rows = $db->Fetch("SELECT `" . $columns['key'] . "` AS id FROM `" . $table . "` WHERE `image` IS NOT NULL AND `media_id` IS NULL", null);
        if ($rows[0] == '00000') {
            continue;
        }
        $moved = 0;
        foreach ($rows as $row) {
            //one row at a time, images can be several MB each
            $item = $db->Fetch("SELECT `" . $columns['owner'] . "` AS owner, `image` FROM `" . $table . "` WHERE `" . $columns['key'] . "` = :a", array(":a" => $row->id));
            try {
                $media_id = Media::store($item[0]->image, $item[0]->owner);
            } catch (Exception $e) {
                echo "<br>" . $table . " " . $row->id . ": image skipped (" . escape($e->getMessage()) . ")";
                continue;
            }
            $db->Fetch("UPDATE `" . $table . "` SET `media_id` = :b WHERE `" . $columns['key'] . "` = :a", array(":a" => $row->id, ":b" => $media_id));
            $moved++;
        }
        echo "<br>moved " . $moved . " " . $table . " images to the media store";
    }
}

/**
 * schema 7: drop the image BLOB columns emptied by media_migrate_blobs() (D11). A table that still
 * has an image that wasn't moved keeps its column, so nothing is lost.
 */
function media_drop_blobs()
{
    foreach (array("users", "garments", "outfits") as $table) {
        if (!column_exists($table, "image")) {
            continue;
        }
        $db = new Mysql();
        $left = $db->Fetch("SELECT COUNT(*) AS n FROM `" . $table . "` WHERE `image` IS NOT NULL AND `media_id` IS NULL", null);
        if ((int)$left[0]->n > 0) {
            echo "<br>" . $table . ".image kept: " . (int)$left[0]->n . " images weren't moved to the media store";
            continue;
        }
        $db->Fetch("ALTER TABLE `" . $table . "` DROP COLUMN `image`", null);
        echo "<br>dropped " . $table . ".image";
    }
}

function follows_check_table()
{
    $sql = "SHOW TABLES LIKE 'follows';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (follows)'] ?? null) == "follows") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function follows_create_table()
{
    $sql = "CREATE TABLE follows(
        follow_num int(11) AUTO_INCREMENT,
        follower_id int(11) NOT NULL,
        followed_id int(11) NOT NULL,
        state varchar(16) NOT NULL DEFAULT 'accepted',
        timestamp timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(follow_num),
        UNIQUE KEY follower_followed (follower_id, followed_id),
        KEY followed_state (followed_id, state)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return follows_check_table();
}

function notifications_check_table()
{
    $sql = "SHOW TABLES LIKE 'notifications';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (notifications)'] ?? null) == "notifications") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function notifications_create_table()
{
    $sql = "CREATE TABLE notifications(
        notification_num int(11) AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        type varchar(32) NOT NULL,
        actor_id int(11) NULL,
        object varchar(128) NULL,
        read_at timestamp NULL DEFAULT NULL,
        timestamp timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(notification_num),
        KEY user_read (user_id, read_at)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return notifications_check_table();
}

function jobs_check_table()
{
    $sql = "SHOW TABLES LIKE 'jobs';";

    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array)$table[0];

    if ($table != '00000') {
        if (($table['Tables_in_' . $_ENV['DB_SCHEMA'] . ' (jobs)'] ?? null) == "jobs") {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function jobs_create_table()
{
    $sql = "CREATE TABLE jobs(
        job_num int(11) AUTO_INCREMENT,
        type varchar(64) NOT NULL,
        payload text NULL,
        run_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        attempts int(11) NOT NULL DEFAULT 0,
        last_error varchar(1024) NULL,
        locked_at timestamp NULL DEFAULT NULL,
        lock_token varchar(64) NULL,
        done_at timestamp NULL DEFAULT NULL,
        failed_at timestamp NULL DEFAULT NULL,
        timestamp timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(job_num),
        KEY due (done_at, failed_at, run_at)
    );";

    $db = new Mysql();
    $db->Fetch($sql, null);

    return jobs_check_table();
}

function password_resets_check_table()
{
    $db = new Mysql();
    $row = $db->Fetch("SHOW TABLES LIKE 'password_resets'", null);
    return $row[0] != '00000';
}

function password_resets_create_table()
{
    //only a hash of the token is stored, so a database leak can't be used to reset passwords
    $sql = "CREATE TABLE password_resets(
        reset_num int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        token_hash char(64) NOT NULL,
        expires_at timestamp NULL DEFAULT NULL,
        used_at timestamp NULL DEFAULT NULL,
        timestamp timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (reset_num),
        UNIQUE KEY token_hash (token_hash),
        KEY user_id (user_id)
    ) CHARACTER SET utf8mb4";
    $db = new Mysql();
    $db->Fetch($sql, null);
    echo "<br>created password_resets table";
}

function column_exists($table, $column)
{
    $params = array(':a' => $_ENV['DB_SCHEMA'], ':b' => $table, ':c' => $column);
    $sql = "SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :a AND `TABLE_NAME` = :b AND `COLUMN_NAME` = :c";

    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    return $row[0] != '00000';
}

function schema_upgrade($columns, $migrations, $schema_version)
{
    $db = new Mysql();

    //the first garments table used an int garment_id as its key
    if (!column_exists("garments", "garment_num")) {
        $db->Fetch("ALTER TABLE `garments` CHANGE `garment_id` `garment_num` int(11) NOT NULL AUTO_INCREMENT", null);
    }

    foreach ($columns as $table => $table_columns) {
        foreach ($table_columns as $column => $definition) {
            if (!column_exists($table, $column)) {
                $db->Fetch("ALTER TABLE `" . $table . "` ADD COLUMN `" . $column . "` " . $definition, null);
                echo "<br>added " . $table . "." . $column;
            }
        }
    }

    $current = (int)setting_get("schema_version", 0);
    foreach ($migrations as $version => $statements) {
        if ($version > $current) {
            foreach ($statements as $statement) {
                //a statement can be the name of a PHP migration function, for data moves SQL can't do
                if (function_exists($statement)) {
                    $statement();
                } else {
                    $db->Fetch($statement, null);
                }
            }
            //saved after every step so a re-run after a failure never repeats one (schema 1 remaps permissions)
            setting_set("schema_version", $version, "int");
            echo "<br>migrated to schema " . $version;
        }
    }

    setting_set("schema_version", $schema_version, "int");
}

//I've just realised this could be made shorter with bigger macros... i'm dumb but i'll do that later
//
//function installed()
//{
//    return false;
//}
//
////check is installed
//if (installed()) {
//    header("location: /");
//    exit();
//}

//build users table
$user = new User();
$request = array(
    "username" => "admin",
    "email" => "webmaster@" . ($_SERVER['SERVER_NAME'] ?? "localhost"),
    "password" => "root",
    "first_name" => "web",
    "last_name" => "master"
);
//bin/ootd install names the first admin itself
if (!empty($GLOBALS['cli_admin'])) {
    $request = array_merge($request, $GLOBALS['cli_admin']);
}
if (!users_check_table()) {
    users_create_table();
    $user->create_user($request, true);
} else {
    echo "<br>user table already complete";
}

//build garments table
if (!garments_check_table()) {
    garments_create_table();
} else {
    echo "<br>garments table already complete";
}

//build outfits table
if (!outfits_check_table()) {
    outfits_create_table();
} else {
    echo "<br>outfits table already complete";
}

//build blogs table
if (!blogs_check_table()) {
    blogs_create_table();
} else {
    echo "<br>blogs table already complete";
}

//some early installs have an invites table from a different design (invite_id, invitee, token, confirmed);
//keep it as invites_legacy and build the current one
if (invites_check_table() && !column_exists("invites", "invite_num")) {
    $legacy_db = new Mysql();
    $legacy_db->Fetch("RENAME TABLE `invites` TO `invites_legacy`", null);
    echo "<br>renamed the old invites table to invites_legacy";
}

//build invites table
if (!invites_check_table()) {
    invites_create_table();
} else {
    echo "<br>invites table already complete";
}

//build media table
if (!media_check_table()) {
    media_create_table();
} else {
    echo "<br>media table already complete";
}

//build follows table
if (!follows_check_table()) {
    follows_create_table();
} else {
    echo "<br>follows table already complete";
}

//build notifications table
if (!notifications_check_table()) {
    notifications_create_table();
} else {
    echo "<br>notifications table already complete";
}

//build jobs table
if (!jobs_check_table()) {
    jobs_create_table();
} else {
    echo "<br>jobs table already complete";
}

//build password_resets table
if (!password_resets_check_table()) {
    password_resets_create_table();
} else {
    echo "<br>password_resets table already complete";
}

//build wears table
if (!wears_check_table()) {
    wears_create_table();
} else {
    echo "<br>wears table already complete";
}

//build permissions table
if (!permissions_check_table()) {
    if (permissions_create_table()) {
        permissions_import($permissions);
    }
} elseif (permissions_count_rows() == 0) {
    permissions_import($permissions);
} elseif (permissions_count_rows() == count($permissions)) {
    echo "<br>permissions table already complete";
} else {
    throw new Exception("Unexpected permissions table error");
}

//build settings table
if (!settings_check_table()) {
    if (settings_create_table()) {
        settings_import($settings);
    }
} elseif (count(settings_missing($settings)) > 0) {
    settings_import(settings_missing($settings));
} else {
    echo "<br>settings table already complete";
}

//build brands table
if (!brands_check_table()) {
    if (brands_create_table()) {
        brands_import($brands);
    }
} elseif (brands_count_rows() == 0) {
    brands_import($brands);
} elseif (brands_count_rows() == count($brands)) {
    echo "<br>brands table already complete";
} else {
    throw new Exception("Unexpected brands table error");
}

//build categories table
if (!categories_check_table()) {
    if (categories_create_table()) {
        categories_import($categories);
    }
} elseif (categories_count_rows() == 0) {
    categories_import($categories);
} elseif (categories_count_rows() == count($categories)) {
    echo "<br>categories table already complete";
} else {
    throw new Exception("Unexpected categories table error");
}
//bring older installs up to the current schema
schema_upgrade($columns, $migrations, $schema_version);

setting_set("installed", 1, "bool");

header("location: /install.php?install=complete");
