<?php
include("./init.php");

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
    )
);

$permissions = array("admin", "moderator", "user");

function brands_check_table(){
    $sql = "SHOW TABLES LIKE 'brands';";
    
    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array) $table[0];

    if($table != '00000'){
        if($table['Tables_in_'.$_ENV['DB_SCHEMA'].' (brands)'] == "brands"){
            return true;
        }else{
            return false;
        }
    }else{
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

    $count = (array) $row[0];

    return $count['COUNT(*)'];
}

function brands_import($brands)
{
    $sql = "INSERT INTO brands(brand_name) VALUES";
    
    foreach($brands as $key=>$brand){
        $sql .= "(\"".$brand."\")";
        if($key < count($brands)-1){
            $sql .= ", ";
        }
    }

    //echo $sql;
    
    $db = new Mysql();
    $db->Fetch($sql, null);

    if(count($brands) == brands_count_rows()){
        return true;
    }else{
        throw new Exception("brand import unsuccesful ".count($brands)." / ".brands_count_rows());
    }

}

function categories_check_table(){
    $sql = "SHOW TABLES LIKE 'categories';";
    
    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array) $table[0];

    if($table != '00000'){
        if($table['Tables_in_'.$_ENV['DB_SCHEMA'].' (categories)'] == "categories"){
            return true;
        }else{
            return false;
        }
    }else{
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

    $count = (array) $row[0];

    return $count['COUNT(*)'];
}

function categories_import($categories)
{
    $sql = "INSERT INTO categories(categories, level, in_moderation) VALUES";
    
    $count = 0;
    foreach($categories as $category=>$options){
        $sql .= "(\"";
        $sql .= $category."\",";
        $sql .= $options['level'].",";
        $sql .= $options['in_moderation'].")";
        if($count < count($categories)-1){
            $sql .= ", ";
        }
        $count ++;
    }
    
    $db = new Mysql();
    $db->Fetch($sql, null);

    if(count($categories) == categories_count_rows()){
        return true;
    }else{
        throw new Exception("categories import unsuccesful ".count($categories)." / ".categories_count_rows());
    }

}

function settings_check_table(){
    $sql = "SHOW TABLES LIKE 'settings';";
    
    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array) $table[0];

    if($table != '00000'){
        if($table['Tables_in_'.$_ENV['DB_SCHEMA'].' (settings)'] == "settings"){
            return true;
        }else{
            return false;
        }
    }else{
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

    $count = (array) $row[0];

    return $count['COUNT(*)'];
}

function settings_import($settings)
{
    $sql = "INSERT INTO settings(setting, type, value) VALUES";
    
    $count = 0;
    foreach($settings as $setting=>$options){
        $sql .= "(\"";
        $sql .= $setting."\",";
        $sql .= "\"".$options['type']."\",";
        $sql .= "\"".$options['value']."\")";
        if($count < count($settings)-1){
            $sql .= ", ";
        }
        $count ++;
    }
    
    $db = new Mysql();
    $db->Fetch($sql, null);

    if(count($settings) == settings_count_rows()){
        return true;
    }else{
        throw new Exception("settings import unsuccesful ".count($settings)." / ".settings_count_rows());
    }

}

function permissions_check_table(){
    $sql = "SHOW TABLES LIKE 'permissions';";
    
    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array) $table[0];

    if($table != '00000'){
        if($table['Tables_in_'.$_ENV['DB_SCHEMA'].' (permissions)'] == "permissions"){
            return true;
        }else{
            return false;
        }
    }else{
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

    $count = (array) $row[0];

    return $count['COUNT(*)'];
}

function permissions_import($permissions)
{
    $sql = "INSERT INTO permissions(permission) VALUES";
    
    foreach($permissions as $key=>$permission){
        $sql .= "(\"".$permission."\")";
        if($key < count($permissions)-1){
            $sql .= ", ";
        }
    }
    
    $db = new Mysql();
    $db->Fetch($sql, null);

    if(count($permissions) == permissions_count_rows()){
        return true;
    }else{
        throw new Exception("permission import unsuccesful ".count($permissions)." / ".permissions_count_rows());
    }

}

function users_check_table(){
    $sql = "SHOW TABLES LIKE 'users';";
    
    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array) $table[0];

    if($table != '00000'){
        if($table['Tables_in_'.$_ENV['DB_SCHEMA'].' (users)'] == "users"){
            return true;
        }else{
            return false;
        }
    }else{
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
        permission int(11) DEFAULT 1,
        session	varchar(32) NULL,
        PRIMARY KEY(user_id)
    );";
    
    $db = new Mysql();
    $db->Fetch($sql, null);
    
    return users_check_table();
}

function garments_check_table(){
    $sql = "SHOW TABLES LIKE 'garments';";
    
    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array) $table[0];

    if($table != '00000'){
        if($table['Tables_in_'.$_ENV['DB_SCHEMA'].' (garments)'] == "garments"){
            return true;
        }else{
            return false;
        }
    }else{
        return false;
    }
}

function garments_create_table()
{
    $sql = "CREATE TABLE garments(
        garment_id	int(11) AUTO_INCREMENT,
        garment_name varchar(128) NULL,
        description varchar(1024) NULL,
        tags varchar(512) NULL,
        image blob NULL,
        PRIMARY KEY(garment_id)
    );";
    
    $db = new Mysql();
    $db->Fetch($sql, null);
    
    return garments_check_table();
}


function outfits_check_table(){
    $sql = "SHOW TABLES LIKE 'outfits';";
    
    $db = new Mysql();
    $table = $db->Fetch($sql, null);

    $table = (array) $table[0];

    if($table != '00000'){
        if($table['Tables_in_'.$_ENV['DB_SCHEMA'].' (outfits)'] == "outfits"){
            return true;
        }else{
            return false;
        }
    }else{
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
        image blob NULL,
        PRIMARY KEY(outfit_num)
    );";
    
    $db = new Mysql();
    $db->Fetch($sql, null);
    
    return outfits_check_table();
}

//i've just realised this could be made shorter with bigger macros... i'm dumb but i'll do that later

function installed()
{
    return false;
}

//check is installed
if(installed()){
    header("location: /");
    exit();
}

//build users table
$user = new User();
$request = array(
    "username"      =>  "admin",
    "email"         =>  "webmaster@".$_SERVER['SERVER_NAME'],
    "password"      =>  "root",
    "first_name"    =>  "web",
    "last_name"     =>  "master"
);
if(!users_check_table()){
    users_create_table();
    $user->create_user($request, true);
}else{
    echo "<br>user table already complete";
}

//build garments table
if(!garments_check_table()){
    garments_create_table();
}else{
    echo "<br>garments table already complete";
}

//build garments table
if(!outfits_check_table()){
    outfits_create_table();
}else{
    echo "<br>outfits table already complete";
}

//build permissions table
if(!permissions_check_table()){
    if(permissions_create_table()){
        permissions_import($permissions);
    }
}elseif(permissions_count_rows() == 0){
    permissions_import($permissions);
}elseif(permissions_count_rows() == count($permissions)){
    echo "<br>permissions table already complete";
}else{
    throw new Exception("Unexpected permissions table error");
}

//build settings table
if(!settings_check_table()){
    if(settings_create_table()){
        settings_import($settings);
    }
}elseif(settings_count_rows() == 0){
    settings_import($settings);
}elseif(settings_count_rows() == count($settings)){
    echo "<br>settings table already complete";
}else{
    throw new Exception("Unexpected settings table error");
}

//build brands table
if(!brands_check_table()){
    if(brands_create_table()){
        brands_import($brands);
    }
}elseif(brands_count_rows() == 0){
    brands_import($brands);
}elseif(brands_count_rows() == count($brands)){
    echo "<br>brands table already complete";
}else{
    throw new Exception("Unexpected brands table error");
}

//build categories table
if(!categories_check_table()){
    if(categories_create_table()){
        categories_import($categories);
    }
}elseif(categories_count_rows() == 0){
    categories_import($categories);
}elseif(categories_count_rows() == count($categories)){
    echo "<br>categories table already complete";
}else{
    throw new Exception("Unexpected categories table error");
}

