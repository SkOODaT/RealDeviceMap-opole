<?php
require_once './config.php';
include_once './static/data/pokedex.php';

$html = "
<ul class='nav nav-pills mb-3 justify-content-center' role='tablist'>
  <li class='nav-item'><a class='nav-link active' role='tab' aria-controls='pokemon' aria-selected='true' data-toggle='pill' href='#pokemon'>Pokemon</a></li>
  <li class='nav-item'><a class='nav-link' role='tab' aria-controls='raids' aria-selected='false' data-toggle='pill' href='#raids'>Raids</a></li>
  <li class='nav-item'><a class='nav-link' role='tab' aria-controls='quests' aria-selected='false' data-toggle='pill' href='#quests'>Quests</a></li>
</ul>

<div class='tab-content'>
  <div id='pokemon' class='tab-pane fade show active' role='tabpanel'>
    <div class='container'>
      <div class='row'>
        <div class='input-group mb-3'>
          <div class='input-group-prepend'>
            <label class='input-group-text' for='filter-date'>Date</label>
          </div>
	        <input id='filter-date' type='text' class='form-control' data-toggle='datepicker'>
          <div class='input-group-prepend'>
            <label class='input-group-text' for='filter-pokemon'>Pokemon</label>
          </div>
          <select id='filter-pokemon' class='custom-select'>
            <option disabled selected>Select</option>
		        <option value='all'>All</option>";
		        foreach ($pokedex as $pokemon_id => $name) {
		          if ($pokemon_id === 0)
                continue;
		          $html .= "<option value='$pokemon_id'>$name</option>";
		        }
		        $html .= "
          </select>
	      </div>
      </div>
    </div>
    <canvas id='pokemon-stats'></canvas>
    <progress id='pokemon-animation' max='1' value='0' style='width: 100%'></progress>
  </div>
  <div id='raids' class='tab-pane fade' role='tabpanel'>
    <div class='container'>
      <div class='row'>
        <div class='input-group mb-3'>
          <div class='input-group-prepend'>
            <label class='input-group-text' for='filter-raid-date'>Date</label>
          </div>
          <input id='filter-raid-date' type='text' class='form-control' data-toggle='datepicker'>
          <div class='input-group-prepend'>
            <label class='input-group-text' for='filter-raid-type'>Filter By</label>
          </div>
          <label class='radio-inline'><input type='radio' class='btn' name='filter-raid-type' value='0' checked>Pokemon</label>
          <label class='radio-inline'><input type='radio' class='btn' name='filter-raid-type' value='1'>Level</label>
        </div>
      </div>
    </div>
    <canvas id='raid-stats'></canvas>
    <progress id='raid-animation' max='1' value='0' style='width: 100%'></progress>
  </div>
  <div id='quests' class='tab-pane fade' role='tabpanel'>
    <canvas id='quest-stats'></canvas>
  </div>
</div>
";
echo $html;
?>

<script type='text/javascript' src='https://fengyuanchen.github.io/datepicker/js/datepicker.js'></script>
<script type='text/javascript' src='https://www.chartjs.org/dist/2.7.3/Chart.bundle.js'></script>
<script type='text/javascript'>
var pkmnProgress = document.getElementById("pokemon-animation");
var raidProgress = document.getElementById("raid-animation");
var pkmnCtx = $("#pokemon-stats");
pkmnGraph = new Chart(pkmnCtx, {
  type: 'bar',
  //data: createChartData("Seen", pokemon, amounts),
  options: createChartOptions("Pokemon Spawn Statistics", "Pokemon", "Amount Seen", pkmnProgress, "pokemon-stats")
});

$("#pokemon-stats").hide();
$("#raid-stats").hide();

var raidCtx = $("#raid-stats");
raidGraph = new Chart(raidCtx, {
  type: 'bar',
  //data: createChartData("Seen", pokemon, amounts),
  options: createChartOptions("Raid Boss Statistics", "Pokemon", "Amount Seen", raidProgress, "raid-stats")
});

$("#filter-raid-level").prop("disabled", true);
$("[data-toggle='datepicker']").datepicker({
  autoHide: true,
  yearFirst: true,
  format: "yyyy-mm-dd",
  zIndex: 2048,
});
$("#filter-date").datepicker("setDate", new Date());
$("#filter-raid-date").datepicker("setDate", new Date());

$("#filter-date").change(filterPokemonChart);
$("#filter-pokemon").change(filterPokemonChart);
filterPokemonChart();

//$("[name='filter-raid-type']").change(filterRaidChart);
$("#filter-raid-date").change(filterRaidChart);
filterRaidChart();

function sendRequest(options, successCallback) {
  $.ajax({
    url: "api.php",
    method: "POST",
    data: options,
    success: successCallback,
    error: function(data) {
      console.log(data);
    }
  });
}

function filterPokemonChart() {
  var date_filter = document.getElementById("filter-date").value;
  var pokemon_filter = document.getElementById("filter-pokemon").value;
  if (pokemon_filter.toLowerCase().indexOf("select") === 0) {
      pokemon_filter = "all";
  }
  if (date_filter != null) {
    console.log("Updating pokemon chart...");
    updatePokemonChart(pkmnGraph, date_filter, pokemon_filter);
  }
}

function filterRaidChart() {
  var date_filter = document.getElementById("filter-raid-date").value;
  var type_filter = $("[name='filter-raid-type']:checked").val();
  if (date_filter != null) {
    console.log("Updating raid chart...");
    updateRaidChart(raidGraph, date_filter, type_filter);
  }
}

function updatePokemonChart(chart, dateFilter, pokeFilter) {
  console.log("Date:",dateFilter,"Pokemon:",pokeFilter);
  var tmp = createToken();
  sendRequest({ "table": "pokemon_stats", "token": tmp }, function(data) {
    this.tmp = null;
    var pokemon = [];
    var amounts = [];
    var obj = JSON.parse(data);
    obj.forEach(stat => {
      if (stat.date === dateFilter && (pokeFilter === stat.pokemon_id || pokeFilter === "all")) {
        pokemon.push(pokedex[stat.pokemon_id]);
        amounts.push(stat.count);
      }
    });

    clearChartData(chart);

    chart.data = createChartData("Seen", pokemon, amounts);
    chart.update();
    console.log("Pokemon chart updated");
  });
}

function updateRaidChart(chart, dateFilter, typeFilter) {
  console.log("Date:",dateFilter,"Type:",typeFilter);
  var tmp = createToken();
  sendRequest({ "table": "raid_stats", "token": tmp }, function(data) {
    this.tmp = null;
    var pokemon = [];
    var amounts = [];
    var obj = JSON.parse(data);
    obj.forEach(stat => {
      if (stat.date === dateFilter) {
        pokemon.push(pokedex[stat.pokemon_id] + " (Level " + stat.level + ")");
        amounts.push(stat.count);
      }
    });

    clearChartData(chart);

    chart.data = createChartData("Seen", pokemon, amounts);
    chart.update();
    console.log("Raid chart updated");
  });
}

function createChartOptions(title, xAxesLabel, yAxesLabel, progress, canvasId) {
  var chartOptions = {
    responsive: true,
    title: { display: true, text: title, fontSize: 18, fontColor: "#111" },
    tooltips: { mode: "index", intersect: false, },
    hover: { mode: "nearest", intersect: true },
    scales: {
      xAxes: [{
        display: true,
        scaleLabel: { display: true, labelString: xAxesLabel }
      }],
      yAxes: [{
        display: true,
        scaleLabel: { display: true, labelString: yAxesLabel },
        ticks: { precision: 0, beginAtZero: true }
      }]
    },
    animation: {
      duration: 2000,
      onProgress: function(animation) {
        progress.value = animation.currentStep / animation.numSteps;
      },
      onComplete: function() {
        window.setTimeout(function() {
          progress.value = 0;
          progress.style.display = "none";
          $("#" + canvasId).show();
        }, 2000);
      }
    }
  };
  return chartOptions;
}

function createChartData(title, labels, data) {
  var colors = [];
  for (var i = 0; i < labels.length; i++) {
    colors.push(getRandomColor());
  }
  var chartData = {
    labels: labels,
    datasets : [{
      label: title,
      strokeColor: createArrayOfValue("<?=$config['ui']['charts']['colors']['stroke']?>", labels.length),
      highlightFill: createArrayOfValue("<?=$config['ui']['charts']['colors']['highlightFill']?>", labels.length),
      highlightStroke: createArrayOfValue("<?=$config['ui']['charts']['colors']['highlightStroke']?>", labels.length),
      backgroundColor: colors,
      borderColor: createArrayOfValue("<?=$config['ui']['charts']['colors']['border']?>", labels.length),
      hoverBackgroundColor: createArrayOfValue("<?=$config['ui']['charts']['colors']['hoverBackground']?>", labels.length),
      hoverBorderColor: createArrayOfValue("<?=$config['ui']['charts']['colors']['hoverBorder']?>", labels.length),
      data: data
    }]
  };
  return chartData;
}

function clearChartData(chart) {
  chart.data.labels.pop();
  chart.data.datasets.forEach((dataset) => {
    dataset.data.pop();
  });
}

function getDate() {
  var d = new Date();
  var date = d.getFullYear() + "-" + ("0"+(d.getMonth()+1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2);
  return date;
}

function getRandomColor() {
  var letters = "0123456789ABCDEF";
  var color = '#';
  for (var i = 0; i < 6; i++ ) {
    color += letters[Math.floor(Math.random() * 16)];
  }
  return color;
}

function createArrayOfValue(value, count) {
  var array = [];
  for (var i = 0; i < count; i++) {
    array.push(value);
  }
  return array;
}

function createToken() {
  return "<?=$_SESSION['token']='bin2hex(openssl_random_pseudo_bytes(16))'?>";
}

var pokedex = {
  1: "Bulbasaur",
  2: "Ivysaur",
  3: "Venusaur",
  4: "Charmander",
  5: "Charmeleon",
  6: "Charizard",
  7: "Squirtle",
  8: "Wartortle",
  9: "Blastoise",
  10: "Caterpie",
  11: "Metapod",
  12: "Butterfree",
  13: "Weedle",
  14: "Kakuna",
  15: "Beedrill",
  16: "Pidgey",
  17: "Pidgeotto",
  18: "Pidgeot",
  19: "Rattata",
  20: "Raticate",
  21: "Spearow",
  22: "Fearow",
  23: "Ekans",
  24: "Arbok",
  25: "Pikachu",
  26: "Raichu",
  27: "Sandshrew",
  28: "Sandslash",
  29: "Nidoran-F",
  30: "Nidorina",
  31: "Nidoqueen",
  32: "Nidoran-M",
  33: "Nidorino",
  34: "Nidoking",
  35: "Clefairy",
  36: "Clefable",
  37: "Vulpix",
  38: "Ninetales",
  39: "Jigglypuff",
  40: "Wigglytuff",
  41: "Zubat",
  42: "Golbat",
  43: "Oddish",
  44: "Gloom",
  45: "Vileplume",
  46: "Paras",
  47: "Parasect",
  48: "Venonat",
  49: "Venomoth",
  50: "Diglett",
  51: "Dugtrio",
  52: "Meowth",
  53: "Persian",
  54: "Psyduck",
  55: "Golduck",
  56: "Mankey",
  57: "Primeape",
  58: "Growlithe",
  59: "Arcanine",
  60: "Poliwag",
  61: "Poliwhirl",
  62: "Poliwrath",
  63: "Abra",
  64: "Kadabra",
  65: "Alakazam",
  66: "Machop",
  67: "Machoke",
  68: "Machamp",
  69: "Bellsprout",
  70: "Weepinbell",
  71: "Victreebel",
  72: "Tentacool",
  73: "Tentacruel",
  74: "Geodude",
  75: "Graveler",
  76: "Golem",
  77: "Ponyta",
  78: "Rapidash",
  79: "Slowpoke",
  80: "Slowbro",
  81: "Magnemite",
  82: "Magneton",
  83: "Farfetch'd",
  84: "Doduo",
  85: "Dodrio",
  86: "Seel",
  87: "Dewgong",
  88: "Grimer",
  89: "Muk",
  90: "Shellder",
  91: "Cloyster",
  92: "Gastly",
  93: "Haunter",
  94: "Gengar",
  95: "Onix",
  96: "Drowzee",
  97: "Hypno",
  98: "Krabby",
  99: "Kingler",
  100: "Voltorb",
  101: "Electrode",
  102: "Exeggcute",
  103: "Exeggutor",
  104: "Cubone",
  105: "Marowak",
  106: "Hitmonlee",
  107: "Hitmonchan",
  108: "Lickitung",
  109: "Koffing",
  110: "Weezing",
  111: "Rhyhorn",
  112: "Rhydon",
  113: "Chansey",
  114: "Tangela",
  115: "Kangaskhan",
  116: "Horsea",
  117: "Seadra",
  118: "Goldeen",
  119: "Seaking",
  120: "Staryu",
  121: "Starmie",
  122: "Mr.Mime",
  123: "Scyther",
  124: "Jynx",
  125: "Electabuzz",
  126: "Magmar",
  127: "Pinsir",
  128: "Tauros",
  129: "Magikarp",
  130: "Gyarados",
  131: "Lapras",
  132: "Ditto",
  133: "Eevee",
  134: "Vaporeon",
  135: "Jolteon",
  136: "Flareon",
  137: "Porygon",
  138: "Omanyte",
  139: "Omastar",
  140: "Kabuto",
  141: "Kabutops",
  142: "Aerodactyl",
  143: "Snorlax",
  144: "Articuno",
  145: "Zapdos",
  146: "Moltres",
  147: "Dratini",
  148: "Dragonair",
  149: "Dragonite",
  150: "Mewtwo",
  151: "Mew",
  152: "Chikorita",
  153: "Bayleef",
  154: "Meganium",
  155: "Cyndaquil",
  156: "Quilava",
  157: "Typhlosion",
  158: "Totodile",
  159: "Croconaw",
  160: "Feraligatr",
  161: "Sentret",
  162: "Furret",
  163: "Hoothoot",
  164: "Noctowl",
  165: "Ledyba",
  166: "Ledian",
  167: "Spinarak",
  168: "Ariados",
  169: "Crobat",
  170: "Chinchou",
  171: "Lanturn",
  172: "Pichu",
  173: "Cleffa",
  174: "Igglybuff",
  175: "Togepi",
  176: "Togetic",
  177: "Natu",
  178: "Xatu",
  179: "Mareep",
  180: "Flaaffy",
  181: "Ampharos",
  182: "Bellossom",
  183: "Marill",
  184: "Azumarill",
  185: "Sudowoodo",
  186: "Politoed",
  187: "Hoppip",
  188: "Skiploom",
  189: "Jumpluff",
  190: "Aipom",
  191: "Sunkern",
  192: "Sunflora",
  193: "Yanma",
  194: "Wooper",
  195: "Quagsire",
  196: "Espeon",
  197: "Umbreon",
  198: "Murkrow",
  199: "Slowking",
  200: "Misdreavus",
  201: "Unown",
  202: "Wobbuffet",
  203: "Girafarig",
  204: "Pineco",
  205: "Forretress",
  206: "Dunsparce",
  207: "Gligar",
  208: "Steelix",
  209: "Snubbull",
  210: "Granbull",
  211: "Qwilfish",
  212: "Scizor",
  213: "Shuckle",
  214: "Heracross",
  215: "Sneasel",
  216: "Teddiursa",
  217: "Ursaring",
  218: "Slugma",
  219: "Magcargo",
  220: "Swinub",
  221: "Piloswine",
  222: "Corsola",
  223: "Remoraid",
  224: "Octillery",
  225: "Delibird",
  226: "Mantine",
  227: "Skarmory",
  228: "Houndour",
  229: "Houndoom",
  230: "Kingdra",
  231: "Phanpy",
  232: "Donphan",
  233: "Porygon2",
  234: "Stantler",
  235: "Smeargle",
  236: "Tyrogue",
  237: "Hitmontop",
  238: "Smoochum",
  239: "Elekid",
  240: "Magby",
  241: "Miltank",
  242: "Blissey",
  243: "Raikou",
  244: "Entei",
  245: "Suicune",
  246: "Larvitar",
  247: "Pupitar",
  248: "Tyranitar",
  249: "Lugia",
  250: "Ho-Oh",
  251: "Celebi",
  252: "Treecko",
  253: "Grovyle",
  254: "Sceptile",
  255: "Torchic",
  256: "Combusken",
  257: "Blaziken",
  258: "Mudkip",
  259: "Marshtomp",
  260: "Swampert",
  261: "Poochyena",
  262: "Mightyena",
  263: "Zigzagoon",
  264: "Linoone",
  265: "Wurmple",
  266: "Silcoon",
  267: "Beautifly",
  268: "Cascoon",
  269: "Dustox",
  270: "Lotad",
  271: "Lombre",
  272: "Ludicolo",
  273: "Seedot",
  274: "Nuzleaf",
  275: "Shiftry",
  276: "Taillow",
  277: "Swellow",
  278: "Wingull",
  279: "Pelipper",
  280: "Ralts",
  281: "Kirlia",
  282: "Gardevoir",
  283: "Surskit",
  284: "Masquerain",
  285: "Shroomish",
  286: "Breloom",
  287: "Slakoth",
  288: "Vigoroth",
  289: "Slaking",
  290: "Nincada",
  291: "Ninjask",
  292: "Shedinja",
  293: "Whismur",
  294: "Loudred",
  295: "Exploud",
  296: "Makuhita",
  297: "Hariyama",
  298: "Azurill",
  299: "Nosepass",
  300: "Skitty",
  301: "Delcatty",
  302: "Sableye",
  303: "Mawile",
  304: "Aron",
  305: "Lairon",
  306: "Aggron",
  307: "Meditite",
  308: "Medicham",
  309: "Electrike",
  310: "Manectric",
  311: "Plusle",
  312: "Minun",
  313: "Volbeat",
  314: "Illumise",
  315: "Roselia",
  316: "Gulpin",
  317: "Swalot",
  318: "Carvanha",
  319: "Sharpedo",
  320: "Wailmer",
  321: "Wailord",
  322: "Numel",
  323: "Camerupt",
  324: "Torkoal",
  325: "Spoink",
  326: "Grumpig",
  327: "Spinda",
  328: "Trapinch",
  329: "Vibrava",
  330: "Flygon",
  331: "Cacnea",
  332: "Cacturne",
  333: "Swablu",
  334: "Altaria",
  335: "Zangoose",
  336: "Seviper",
  337: "Lunatone",
  338: "Solrock",
  339: "Barboach",
  340: "Whiscash",
  341: "Corphish",
  342: "Crawdaunt",
  343: "Baltoy",
  344: "Claydol",
  345: "Lileep",
  346: "Cradily",
  347: "Anorith",
  348: "Armaldo",
  349: "Feebas",
  350: "Milotic",
  351: "Castform",
  352: "Kecleon",
  353: "Shuppet",
  354: "Banette",
  355: "Duskull",
  356: "Dusclops",
  357: "Tropius",
  358: "Chimecho",
  359: "Absol",
  360: "Wynaut",
  361: "Snorunt",
  362: "Glalie",
  363: "Spheal",
  364: "Sealeo",
  365: "Walrein",
  366: "Clamperl",
  367: "Huntail",
  368: "Gorebyss",
  369: "Relicanth",
  370: "Luvdisc",
  371: "Bagon",
  372: "Shelgon",
  373: "Salamence",
  374: "Beldum",
  375: "Metang",
  376: "Metagross",
  377: "Regirock",
  378: "Regice",
  379: "Registeel",
  380: "Latias",
  381: "Latios",
  382: "Kyogre",
  383: "Groudon",
  384: "Rayquaza",
  385: "Jirachi",
  386: "Deoxys",
  387: "Turtwig",
  388: "Grotle",
  389: "Torterra",
  390: "Chimchar",
  391: "Monferno",
  392: "Infernape",
  393: "Piplup",
  394: "Prinplup",
  395: "Empoleon",
  396: "Starly",
  397: "Staravia",
  398: "Staraptor",
  399: "Bidoof",
  400: "Bibarel",
  401: "Kricketot",
  402: "Kricketune",
  403: "Shinx",
  404: "Luxio",
  405: "Luxray",
  406: "Budew",
  407: "Roserade",
  408: "Cranidos",
  409: "Rampardos",
  410: "Shieldon",
  411: "Bastiodon",
  412: "Burmy",
  413: "Wormadam",
  414: "Mothim",
  415: "Combee",
  416: "Vespiquen",
  417: "Pachirisu",
  418: "Buizel",
  419: "Floatzel",
  420: "Cherubi",
  421: "Cherrim",
  422: "Shellos",
  423: "Gastrodon",
  424: "Ambipom",
  425: "Drifloon",
  426: "Drifblim",
  427: "Buneary",
  428: "Lopunny",
  429: "Mismagius",
  430: "Honchkrow",
  431: "Glameow",
  432: "Purugly",
  433: "Chingling",
  434: "Stunky",
  435: "Skuntank",
  436: "Bronzor",
  437: "Bronzong",
  438: "Bonsly",
  439: "MimeJr.",
  440: "Happiny",
  441: "Chatot",
  442: "Spiritomb",
  443: "Gible",
  444: "Gabite",
  445: "Garchomp",
  446: "Munchlax",
  447: "Riolu",
  448: "Lucario",
  449: "Hippopotas",
  450: "Hippowdon",
  451: "Skorupi",
  452: "Drapion",
  453: "Croagunk",
  454: "Toxicroak",
  455: "Carnivine",
  456: "Finneon",
  457: "Lumineon",
  458: "Mantyke",
  459: "Snover",
  460: "Abomasnow",
  461: "Weavile",
  462: "Magnezone",
  463: "Lickilicky",
  464: "Rhyperior",
  465: "Tangrowth",
  466: "Electivire",
  467: "Magmortar",
  468: "Togekiss",
  469: "Yanmega",
  470: "Leafeon",
  471: "Glaceon",
  472: "Gliscor",
  473: "Mamoswine",
  474: "Porygon-Z",
  475: "Gallade",
  476: "Probopass",
  477: "Dusknoir",
  478: "Froslass",
  479: "Rotom",
  480: "Uxie",
  481: "Mesprit",
  482: "Azelf",
  483: "Dialga",
  484: "Palkia",
  485: "Heatran",
  486: "Regigigas",
  487: "Giratina",
  488: "Cresselia",
  489: "Phione",
  490: "Manaphy",
  491: "Darkrai",
  492: "Shaymin",
  493: "Arceus",
  808: "Meltan",
  809: "Melmetal"
};
</script>