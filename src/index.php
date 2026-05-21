<?php
/**
 * Shopify & Liquid Learning Platform - Main Router & Controller
 * Implements authentication, seeding, dynamic document tree, exercises engine, and administrative controls.
 */

session_start();

// Include database ORM and Markdown parser
require_once __DIR__ . '/rb-postgres.php';
require_once __DIR__ . '/markdown-parser.php';

use R as R;

// Initialize Database connection
try {
    if (!R::testConnection()) {
        $dbUrl = getenv('DATABASE_URL');
        if ($dbUrl) {
            // Parse standard DATABASE_URL (e.g. postgres://user:pass@host:port/dbname)
            $dbopts = parse_url($dbUrl);
            $db_host = $dbopts["host"];
            $db_port = isset($dbopts["port"]) ? $dbopts["port"] : 5432;
            $db_user = $dbopts["user"];
            $db_pass = $dbopts["pass"];
            $db_name = ltrim($dbopts["path"], '/');
            R::setup("pgsql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_pass);
        } else {
            // Fallback to environment credentials or local defaults
            $db_host = getenv('DB_HOST') ?: 'db';
            $db_port = getenv('DB_PORT') ?: '5432';
            $db_name = getenv('DB_NAME') ?: 'mydatabase';
            $db_user = getenv('DB_USER') ?: 'myuser';
            $db_pass = getenv('DB_PASSWORD') ?: 'mypassword';
            R::setup("pgsql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_pass);
        }
    }
    // Set to fluid mode for dynamic database adjustments
    R::freeze(false);
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// HELPER FUNCTIONS & SEEDING LOGIC
// -----------------------------------------------------------------------------

function get_logged_user() {
    if (isset($_SESSION['user_id'])) {
        return R::load('user', $_SESSION['user_id']);
    }
    return null;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    $user = get_logged_user();
    return $user && $user->role === 'admin';
}

// Dynamic DB Seeding
function seed_database() {
    // 1. Check if mock admin user exists
    $admin = R::findOne('user', 'username = ?', ['Admin']);
    if (!$admin) {
        $admin = R::dispense('user');
        $admin->username = 'Admin';
        $admin->password_hash = password_hash('Admin123', PASSWORD_BCRYPT);
        $admin->role = 'admin';
        R::store($admin);
    }

    // 2. Check and seed documentation if empty or outdated
    $docCount = R::count('document');
    if ($docCount < 15) {
        R::wipe('document');
        $docs = [
            [
                'title' => 'Overview to Shopify & Liquid',
                'slug' => 'overview',
                'category' => 'Getting Started',
                'parent_slug' => '',
                'content' => "# Introduction to Shopify & Liquid\n\nWelcome to the official Shopify Liquid Learning sandbox! Here, you'll learn how Shopify themes render dynamic storefront data.\n\nShopify uses **Liquid**, an open-source, ruby-based template engine. It acts as the bridge between your store's HTML files and database resources (like product data).\n\n### What is Liquid?\nLiquid is a secure, server-side templating language that loads dynamic content on storefronts. It utilizes three core syntax blocks:\n1. **Objects (`{{ double curly braces }}`)**: Tells Liquid where to output content from the database.\n2. **Tags (`{% curly-percent blocks %}`)**: Controls logic, iterations, loops, and setups.\n3. **Filters (`| vertical bars`)**: Modifies the output of objects.\n\nBrowse through the subtopics in the left navigation sidebar to explore individual constructs!",
                'order_index' => 1
            ],
            [
                'title' => 'Liquid Objects & Output',
                'slug' => 'objects',
                'category' => 'Getting Started',
                'parent_slug' => 'overview',
                'content' => "# Liquid Objects\n\nObjects are variables that tell Liquid where to fetch and output data on a page. Liquid displays objects using double curly braces: `{{` and `}}`.\n\nFor example, to display the title of a product:\n```liquid\n{{ product.title }}\n```\nThis outputs the name of the product currently loaded in the database. In our learning environment, this translates to:\n`{{ product.title }}` &rarr; **Shopify Retro Cap**\n\n### Standard Shopify Objects\nHere are some of the primary global objects you can output:\n- `shop.name`: The title of the store.\n- `customer.name`: The full name of the currently logged-in customer.\n- `cart.item_count`: The total quantity of items in the cart.",
                'order_index' => 2
            ],
            [
                'title' => 'Shopify Objects: Product & Variant Details',
                'slug' => 'product-variants',
                'category' => 'Getting Started',
                'parent_slug' => 'overview',
                'content' => "# Product & Variant Details\n\nEvery product inside Shopify contains one or more **Variants** representing different options like size, color, or material. Even if a product has no custom options, it has at least one default variant.\n\n### Accessing Product Fields\n- `product.title`: The name of the product.\n- `product.price`: The price of the product.\n- `product.compare_at_price`: The original price before a sale.\n- `product.available`: Returns `true` if in stock, or `false` if sold out.\n- `product.tags`: An array containing all tags assigned to the product (e.g. `accessories`, `retro`).\n\n### Example Syntax\nTo check if a product is on sale:\n```liquid\n{% if product.compare_at_price > product.price %}\n  <span class=\"sale-tag\">Save {{ product.compare_at_price | minus: product.price | money }}!</span>\n{% endif %}\n```",
                'order_index' => 3
            ],
            [
                'title' => 'Shopify Objects: Cart & Checkout',
                'slug' => 'cart-checkout',
                'category' => 'Getting Started',
                'parent_slug' => 'overview',
                'content' => "# Cart & Line Items\n\nThe `cart` object represents the visitor's current shopping session and holds all products they have added to their cart.\n\n### Key Cart Attributes\n- `cart.item_count`: The total count of all quantities inside the cart.\n- `cart.total_price`: The sum of all item prices in the cart.\n- `cart.items`: An array of `line_item` objects representing individual product rows in the cart.\n\n### Looping through Cart Line Items\nEach `line_item` contains specific properties like `title`, `price`, `quantity`, and `final_line_price`:\n```liquid\n<ul>\n  {% for item in cart.items %}\n    <li>{{ item.quantity }}x {{ item.title }} - {{ item.price | money }}</li>\n  {% endfor %}\n</ul>\n```",
                'order_index' => 4
            ],
            [
                'title' => 'Theme Architecture & Templates',
                'slug' => 'theme-architecture',
                'category' => 'Getting Started',
                'parent_slug' => 'overview',
                'content' => "# Shopify Theme Architecture\n\nShopify storefront designs are organized into highly structured directories inside a theme:\n\n### Core Folders\n1. **`layout/`**: Houses global parent wrappers (usually `theme.liquid`). Controlls headers, footers, and scripts loaded globally.\n2. **`templates/`**: Dictates unique configurations matching page urls (e.g., `templates/product.liquid` for single products, `templates/cart.liquid` for carts).\n3. **`sections/`**: Highly custom modules that can be dragged, dropped, and customized directly in the visual Theme Editor.\n4. **`snippets/`**: Reusable static chunks of code loaded using the `{% render 'snippet-name' %}` tag (great for icons or item grids).",
                'order_index' => 5
            ],
            [
                'title' => 'Liquid Filters',
                'slug' => 'filters',
                'category' => 'Language Guide',
                'parent_slug' => '',
                'content' => "# Liquid Filters\n\nFilters are functions that modify the output of a Liquid object. They are placed within an output tag, separated from the variable by a vertical pipe character `|`.\n\n### Formatting Currency\nOne of the most common filters in Shopify is `money`, which formats a number into a dollar amount:\n```liquid\n{{ product.price | money }}\n```\nIf `product.price` is `29.99`, it outputs `$29.99`.\nTo append a currency label, use `money_with_currency`:\n```liquid\n{{ product.price | money_with_currency }}\n```\nOutputs: `$29.99 USD`.\n\n### String Modifications\n- `upcase`: Converts a string to uppercase. (e.g. `{{ 'liquid' | upcase }}` &rarr; `LIQUID`)\n- `downcase`: Converts a string to lowercase.\n- `append`: Appends text. (e.g. `{{ 'Welcome' | append: ' Admin!' }}` &rarr; `Welcome Admin!`)\n- `size`: Returns the length of a string or array.",
                'order_index' => 6
            ],
            [
                'title' => 'Advanced Filters & Array Manipulation',
                'slug' => 'advanced-filters',
                'category' => 'Language Guide',
                'parent_slug' => 'filters',
                'content' => "# Advanced Filters & Arrays\n\nBeyond basic text modifications, Liquid supports advanced filtering tags to sort, select, or format entire datasets or arrays.\n\n### The Map Filter\n`map` extracts a specific property from an array of objects to create a flat list. For example, to collect all titles of products in a collection:\n```liquid\n{% assign all_titles = collections.frontpage.products | map: 'title' %}\n{{ all_titles | join: ', ' }}\n```\nOutputs: `Sleek Dark Mug, Liquid Developer Tee, Shopify Sticker Pack`.\n\n### Useful Array Filters\n- `uniq`: Removes duplicate items from an array.\n- `reverse`: Reverses the sort index of an array.\n- `first` / `last`: Quickly pulls the initial or terminal item in a list without a loop.",
                'order_index' => 7
            ],
            [
                'title' => 'Control Flow & Conditionals',
                'slug' => 'conditionals',
                'category' => 'Language Guide',
                'parent_slug' => 'filters',
                'content' => "# Control Flow (If / Else)\n\nControl flow tags allow you to use logical statements to determine what markup gets rendered on the page.\n\n### The If Tag\nUse standard logical comparisons (`==`, `!=`, `>`, `<`, `>=`, `<=`, `contains`):\n```liquid\n{% if product.available %}\n  <p>In Stock! Buy now!</p>\n{% else %}\n  <p>Sold Out</p>\n{% endif %}\n```\n\n### Using Contains\n`contains` checks for the presence of a substring in a string, or an element inside an array:\n```liquid\n{% if product.tags contains 'retro' %}\n  <span class=\"badge\">Classic Collection</span>\n{% endif %}\n```",
                'order_index' => 8
            ],
            [
                'title' => 'Loops & Iterations',
                'slug' => 'loops',
                'category' => 'Language Guide',
                'parent_slug' => '',
                'content' => "# Loops & Iteration\n\nLoops output a block of HTML repeatedly for every item in an array or collection.\n\n### Basic For Loop\n```liquid\n<ul>\n  {% for product in collections.frontpage.products %}\n    <li>{{ product.title }} - {{ product.price | money }}</li>\n  {% endfor %}\n</ul>\n```\n\n### Loop Variables\nInside a `for` loop, Liquid provides the `forloop` object to track loop statistics:\n- `forloop.index`: Current index (1-based).\n- `forloop.first`: Returns `true` if it's the first iteration.\n- `forloop.last`: Returns `true` if it's the last iteration.",
                'order_index' => 9
            ],
            [
                'title' => 'Theme Schema & Custom Settings',
                'slug' => 'theme-schema',
                'category' => 'Theme Configuration',
                'parent_slug' => '',
                'content' => "# Theme Schema & Custom Settings\n\nShopify templates and sections allow developers to build extremely custom settings that merchants edit visually in the Admin Theme Editor.\n\n### The Schema Tag\nAt the bottom of a section file (e.g. `sections/featured-product.liquid`), developers write a configuration schema inside the `{% schema %}` tag block:\n```json\n{% schema %}\n{\n  \"name\": \"Featured Product\",\n  \"settings\": [\n    {\n      \"type\": \"text\",\n      \"id\": \"heading\",\n      \"label\": \"Heading Title\",\n      \"default\": \"Deal of the Day\"\n    }\n  ]\n}\n{% endschema %}\n```\nThis JSON config automatically tells Shopify's Theme Editor which fields to render for customization!",
                'order_index' => 10
            ],
            [
                'title' => 'Accessing Settings in Liquid',
                'slug' => 'theme-settings',
                'category' => 'Theme Configuration',
                'parent_slug' => 'theme-schema',
                'content' => "# Accessing Settings in Liquid\n\nOnce schema settings are configured, you can output their merchant-entered values in your section template code.\n\n### Global settings Object\nGlobal settings defined inside the theme-wide `config/settings_schema.json` are retrieved via the `settings` object:\n```liquid\n<body class=\"{{ settings.color_scheme }}\">\n```\n\n### Section settings Object\nSettings defined locally in a section's schema are accessed using the `section.settings` object, limited to that specific block:\n```liquid\n<h2>{{ section.settings.heading }}</h2>\n```\nIf the merchant types \"Summer Sale\" in the sidebar, `section.settings.heading` renders `Summer Sale`!",
                'order_index' => 11
            ],
            [
                'title' => 'Advanced Layouts & Render Tag',
                'slug' => 'theme-layouts',
                'category' => 'Theme Configuration',
                'parent_slug' => '',
                'content' => "# Layouts, Snippets, & Rendering\n\nShopify uses structural layout templates to enclose page layouts, creating reusable structures like global headers and scripts.\n\n### Global Layouts\nBy default, Shopify routes pages through the parent wrapper: `layout/theme.liquid`. In other layout configurations, you can choose custom parent containers using the `{% layout 'custom-layout' %}` tag.\n\n### Reusable Snippets & Render Tag\nFor modular code reuse, save template snippets inside the `snippets/` folder and render them in layout/template grids:\n```liquid\n{% render 'product-card', card_product: product %}\n```\n*Note: The modern `{% render %}` tag replaces the legacy `{% include %}` tag, providing isolated variable scopes and faster, more secure page loads.*",
                'order_index' => 12
            ],
            [
                'title' => 'Dynamic Variable Assignment & Capture',
                'slug' => 'liquid-variables',
                'category' => 'Language Guide',
                'parent_slug' => 'theme-layouts',
                'content' => "# Liquid Variables\n\nLiquid lets you dynamically instantiate and modify local variables using dedicated syntax tags.\n\n### Variable Assignment\nUse `assign` to store basic primitive expressions into variables:\n```liquid\n{% assign discount_price = product.price | times: 0.8 %}\n<p>On Sale for: {{ discount_price | money }}!</p>\n```\n\n### Capturing Output Blocks\nUse `capture` to save a multiline structural HTML render block as a single string variable:\n```liquid\n{% capture promo_badge %}\n  <div class=\"badge-promo\">\n    <strong>HOT DEAL!</strong>\n  </div>\n{% endcapture %}\n\n{{ promo_badge }} <!-- outputs the entire block -->\n```",
                'order_index' => 13
            ],
            [
                'title' => 'Whitespace Control in Liquid',
                'slug' => 'liquid-whitespace',
                'category' => 'Language Guide',
                'parent_slug' => 'theme-layouts',
                'content' => "# Whitespace Control\n\nWhen Liquid renders on the server, it outputs every blank line and carriage return found in the template files, resulting in messy, deeply indented browser source code.\n\n### Stripping Whitespace with Hyphens\nYou can strip leading and trailing whitespace from the rendered output by adding hyphens (`-`) inside the tag boundaries:\n- `{%-` and `-%}` for tags\n- `{{-` and `-}}` for outputs\n\n### Example Comparison\nWithout hyphens:\n```liquid\n{% if true %}\n  Hello\n{% endif %}\n```\nOutputs: `\\n  Hello\\n`.\n\nWith hyphens:\n```liquid\n{%- if true -%}\n  Hello\n{%- endif -%}\n```\nOutputs: `Hello` (no spaces or carriage returns!).",
                'order_index' => 14
            ],
            [
                'title' => 'Shopify Global API Objects',
                'slug' => 'shopify-global-objects',
                'category' => 'Getting Started',
                'parent_slug' => '',
                'content' => "# Shopify Global API Objects\n\nShopify automatically maps extensive backend entities and session logs into global variables accessible in your templates.\n\n### Primary Global Variables\n- `customer`: Details about the logged-in buyer (e.g. `customer.email`, `customer.orders_count`, `customer.total_spent`).\n- `collections`: Dictionary of all collections (e.g. `collections['frontpage'].products`).\n- `search`: Tracking search attributes (e.g. `search.terms`, `search.results_count`).\n- `request`: Current HTTP request attributes.",
                'order_index' => 15
            ]
        ];
 
        foreach ($docs as $d) {
            $doc = R::dispense('document');
            $doc->title = $d['title'];
            $doc->slug = $d['slug'];
            $doc->category = $d['category'];
            $doc->parent_slug = $d['parent_slug'];
            $doc->content = $d['content'];
            $doc->order_index = $d['order_index'];
            R::store($doc);
        }
    }

    // 3. Check and seed exercises if empty or outdated
    $exCount = R::count('exercise');
    if ($exCount < 18) {
        R::wipe('exercise');
        
        // Exercise 1: Multiple Choice
        $ex1 = R::dispense('exercise');
        $ex1->title = "Liquid Output Syntaxes";
        $ex1->category = "Basic Syntax";
        $ex1->type = "choice";
        $ex1->description = "Which syntax represents a correct Liquid variable/object output block?";
        $ex1->question_data = json_encode([
            'options' => [
                '[% product.title %]',
                '{{ product.title }}',
                '[[ product.title ]]',
                '{* product.title *}'
            ],
            'correct_option' => 1,
            'solution' => "In Shopify Liquid, double curly brackets {{ ... }} are used to output dynamic content on a page, while {% ... %} represents logic/tags."
        ]);
        $ex1->order_index = 1;
        R::store($ex1);
 
        // Exercise 2: Multiple Choice
        $ex2 = R::dispense('exercise');
        $ex2->title = "Filtering Prices";
        $ex2->category = "Filters";
        $ex2->type = "choice";
        $ex2->description = "Which filter is used in Shopify Liquid to convert a numeric variable to standard monetary/dollar representation?";
        $ex2->question_data = json_encode([
            'options' => [
                '| currency',
                '| to_dollar',
                '| money',
                '| format_price'
            ],
            'correct_option' => 2,
            'solution' => "The '| money' filter is the standard Shopify Liquid filter for currency formatting. It automatically appends currency symbols and decimal places."
        ]);
        $ex2->order_index = 2;
        R::store($ex2);
 
        // Exercise 3: Multiple Choice (New - Advanced Filters)
        $ex3 = R::dispense('exercise');
        $ex3->title = "Filtering Arrays with Map";
        $ex3->category = "Advanced Filters";
        $ex3->type = "choice";
        $ex3->description = "Which Liquid filter allows you to extract a specific property from an array of objects to build a flat list (for example, gathering all titles from a list of products)?";
        $ex3->question_data = json_encode([
            'options' => [
                '| pluck: \'title\'',
                '| select: \'title\'',
                '| map: \'title\'',
                '| extract: \'title\''
            ],
            'correct_option' => 2,
            'solution' => "The 'map' filter extracts a single property from an array of objects, creating a clean list of primitive values (strings/numbers)."
        ]);
        $ex3->order_index = 3;
        R::store($ex3);
 
        // Exercise 4: Multiple Choice (New - Theme Structure)
        $ex4 = R::dispense('exercise');
        $ex4->title = "Theme Sections vs Snippets";
        $ex4->category = "Theme Architecture";
        $ex4->type = "choice";
        $ex4->description = "What is the primary operational difference between a Shopify section template and a snippet template?";
        $ex4->question_data = json_encode([
            'options' => [
                "Sections support visual customizations via Schema structures in the dynamic Admin Editor; Snippets are static files imported manually.",
                "Snippets support Liquid loops, while Sections are limited to static variables.",
                "Sections are saved within layout files, whereas Snippets are saved directly in local assets folders.",
                "There is no functional difference; they are interchangeable."
            ],
            'correct_option' => 0,
            'solution' => "Sections feature a {% schema %} configuration rendering theme customization options dynamically in Shopify's editor panel. Snippets are loaded using {% render %}."
        ]);
        $ex4->order_index = 4;
        R::store($ex4);
 
        // Exercise 5: Code Exercise (Basic output)
        $ex5 = R::dispense('exercise');
        $ex5->title = "Render Store Name";
        $ex5->category = "Basic Syntax";
        $ex5->type = "code";
        $ex5->description = "Let's output a shop variable! Render the name of the store (located in `shop.name`) inside standard H1 heading tags.";
        $ex5->question_data = json_encode([
            'starter_code' => "<h1>{{ /* Enter variable name here */ }}</h1>",
            'expected_output' => "<h1>Shopify Sandbox</h1>",
            'solution' => "<h1>{{ shop.name }}</h1>"
        ]);
        $ex5->order_index = 5;
        R::store($ex5);
 
        // Exercise 6: Code Exercise (Looping products)
        $ex6 = R::dispense('exercise');
        $ex6->title = "Display Featured Product Titles";
        $ex6->category = "Loops";
        $ex6->type = "code";
        $ex6->description = "Write a loop to iterate through the products in our frontpage collection (`collections.frontpage.products`). Inside each loop iteration, display a list item `<li>` containing the product's title and its price using the `money` filter, separated by ' - '. Keep it formatted inside the surrounding `<ul>` tags.";
        $ex6->question_data = json_encode([
            'starter_code' => "<ul>\n  {% for product in collections.frontpage.products %}\n    <!-- Enter loop content here -->\n  {% endfor %}\n</ul>",
            'expected_output' => "<ul>\n  \n    <li>Sleek Dark Mug - $14.99</li>\n  \n    <li>Liquid Developer Tee - $24.99</li>\n  \n    <li>Shopify Sticker Pack - $4.99</li>\n  \n</ul>",
            'solution' => "<ul>\n  {% for product in collections.frontpage.products %}\n    <li>{{ product.title }} - {{ product.price | money }}</li>\n  {% endfor %}\n</ul>"
        ]);
        $ex6->order_index = 6;
        R::store($ex6);
 
        // Exercise 7: Code Exercise (New - Conditionals)
        $ex7 = R::dispense('exercise');
        $ex7->title = "Conditional Availability Banner";
        $ex7->category = "Control Flow";
        $ex7->type = "code";
        $ex7->description = "Display a special text message based on product availability. If the product is available (`product.available` is true), render `<span class=\"in-stock\">In Stock!</span>`. Otherwise, render `<span class=\"sold-out\">Unavailable</span>`.";
        $ex7->question_data = json_encode([
            'starter_code' => "{% if product.available %}\n  <!-- Render in-stock HTML -->\n{% else %}\n  <!-- Render unavailable HTML -->\n{% endif %}",
            'expected_output' => "<span class=\"in-stock\">In Stock!</span>",
            'solution' => "{% if product.available %}\n  <span class=\"in-stock\">In Stock!</span>\n{% else %}\n  <span class=\"sold-out\">Unavailable</span>\n{% endif %}"
        ]);
        $ex7->order_index = 7;
        R::store($ex7);
 
        // Exercise 8: Code Exercise (New - Filters)
        $ex8 = R::dispense('exercise');
        $ex8->title = "Format Cart Total Price";
        $ex8->category = "Filters";
        $ex8->type = "code";
        $ex8->description = "Write a summary of the cart status. Output the following exact text structure inside standard paragraph tags: `Cart contains X items. Total: Y` - replacing `X` with `cart.item_count` and `Y` with `cart.total_price` formatted with the `money` filter.";
        $ex8->question_data = json_encode([
            'starter_code' => "<p>Cart contains {{ /* ... */ }} items. Total: {{ /* ... */ }}</p>",
            'expected_output' => "<p>Cart contains 3 items. Total: $89.97</p>",
            'solution' => "<p>Cart contains {{ cart.item_count }} items. Total: {{ cart.total_price | money }}</p>"
        ]);
        $ex8->order_index = 8;
        R::store($ex8);
 
        // Exercise 9: Code Exercise (New - Loops/Arrays)
        $ex9 = R::dispense('exercise');
        $ex9->title = "List Product Tags";
        $ex9->category = "Loops";
        $ex9->type = "code";
        $ex9->description = "Loop through the product tags array (`product.tags`) and render each tag inside a span like `<span class=\"tag\">tagname</span>`. Make sure your tags loop is properly closed.";
        $ex9->question_data = json_encode([
            'starter_code' => "<div class=\"tag-list\">\n  <!-- Loop through tags -->\n</div>",
            'expected_output' => "<div class=\"tag-list\">\n  \n    <span class=\"tag\">accessories</span>\n  \n    <span class=\"tag\">clothing</span>\n  \n    <span class=\"tag\">retro</span>\n  \n</div>",
            'solution' => "<div class=\"tag-list\">\n  {% for tag in product.tags %}\n    <span class=\"tag\">{{ tag }}</span>\n  {% endfor %}\n</div>"
        ]);
        $ex9->order_index = 9;
        R::store($ex9);
 
        // Exercise 10: Code Exercise (New - Advanced Assign & Map)
        $ex10 = R::dispense('exercise');
        $ex10->title = "Collect Featured Products Size";
        $ex10->category = "Advanced Filters";
        $ex10->type = "code";
        $ex10->description = "Assign a local variable named `products` to the featured product list (`collections.frontpage.products`). Then, output the total length of the array inside standard span tags like `<span>Featured count: X</span>` replacing X using the `size` filter.";
        $ex10->question_data = json_encode([
            'starter_code' => "{% assign products = /* ... */ %}\n<span>Featured count: {{ /* ... */ }}</span>",
            'expected_output' => "<span>Featured count: 3</span>",
            'solution' => "{% assign products = collections.frontpage.products %}\n<span>Featured count: {{ products | size }}</span>"
        ]);
        $ex10->order_index = 10;
        R::store($ex10);

        // Exercise 11: Multiple Choice
        $ex11 = R::dispense('exercise');
        $ex11->title = "Whitespace Control in Liquid";
        $ex11->category = "Advanced Liquid";
        $ex11->type = "choice";
        $ex11->description = "Which characters are added to Liquid tags or output braces to strip whitespace from the rendered output?";
        $ex11->question_data = json_encode([
            'options' => [
                '{%~ ... ~%} and {{~ ... ~}}',
                '{%- ... -%} and {{- ... -}}',
                '{%_ ... _%} and {{_ ... _}}',
                '{%* ... *%} and {{* ... *}}'
            ],
            'correct_option' => 1,
            'solution' => "Adding hyphens directly inside the brackets (e.g., {%- and -%} or {{- and -}}) automatically strips all leading and trailing whitespace from the rendered HTML."
        ]);
        $ex11->order_index = 11;
        R::store($ex11);

        // Exercise 12: Multiple Choice
        $ex12 = R::dispense('exercise');
        $ex12->title = "Theme Schema Configuration";
        $ex12->category = "Theme Architecture";
        $ex12->type = "choice";
        $ex12->description = "Where inside a Shopify Theme section file is the JSON layout configuration schema stored?";
        $ex12->question_data = json_encode([
            'options' => [
                "Inside {% config %} tags",
                "Inside {% settings %} tags",
                "Inside {% schema %} tags",
                "Inside a separate section.json configuration file"
            ],
            'correct_option' => 2,
            'solution' => "The {% schema %} tag block holds JSON markup defining custom settings and layout blocks editable by merchants inside Shopify's Admin Customizer."
        ]);
        $ex12->order_index = 12;
        R::store($ex12);

        // Exercise 13: Multiple Choice
        $ex13 = R::dispense('exercise');
        $ex13->title = "Liquid Capture Tag vs Assign";
        $ex13->category = "Variables";
        $ex13->type = "choice";
        $ex13->description = "What is the primary difference between the {% assign %} tag and the {% capture %} tag in Liquid?";
        $ex13->question_data = json_encode([
            'options' => [
                "assign handles numbers, while capture handles strings only.",
                "assign evaluates filters, whereas capture cannot use filters.",
                "capture allows you to store a complex block of rendered HTML/text content into a variable, whereas assign is for single-line expressions.",
                "There is no functional difference; they are exact aliases."
            ],
            'correct_option' => 2,
            'solution' => "The {% capture %} tag encloses a multi-line HTML block, saving the entire generated string result into a local variable for later filtering or printing."
        ]);
        $ex13->order_index = 13;
        R::store($ex13);

        // Exercise 14: Multiple Choice
        $ex14 = R::dispense('exercise');
        $ex14->title = "Snippet Loading: Render vs Include";
        $ex14->category = "Theme Architecture";
        $ex14->type = "choice";
        $ex14->description = "Which of the following describes the modern Shopify practice for rendering snippet files?";
        $ex14->question_data = json_encode([
            'options' => [
                "Use {% include 'snippet' %} because it has dynamic scope inheritance.",
                "Use {% render 'snippet' %} because it runs in an isolated scope for better performance and safety.",
                "Use {{ 'snippet' | import }} to output snippets inside assets folders.",
                "Snippets are deprecated and cannot be rendered in modern themes."
            ],
            'correct_option' => 1,
            'solution' => "Modern themes use {% render %}, which creates an isolated variable context inside snippets, preventing parent variables from leaking and optimizing page renders."
        ]);
        $ex14->order_index = 14;
        R::store($ex14);

        // Exercise 15: Code Exercise
        $ex15 = R::dispense('exercise');
        $ex15->title = "Conditional Welcome Message";
        $ex15->category = "Control Flow";
        $ex15->type = "code";
        $ex15->description = "Let's personalize the store welcome banner. If the customer is logged in (customer.is_logged_in is true), render '<p>Welcome back, X! Total spent: Y</p>' replacing X with customer.name and Y with customer.total_spent formatted with the money filter. Otherwise, render '<p>Welcome Guest!</p>'.";
        $ex15->question_data = json_encode([
            'starter_code' => "{% if customer.is_logged_in %}\n  <!-- Render customer welcome -->\n{% else %}\n  <!-- Render guest welcome -->\n{% endif %}",
            'expected_output' => "<p>Welcome back, John Doe! Total spent: $120.50</p>",
            'solution' => "{% if customer.is_logged_in %}\n  <p>Welcome back, {{ customer.name }}! Total spent: {{ customer.total_spent | money }}</p>\n{% else %}\n  <p>Welcome Guest!</p>\n{% endif %}"
        ]);
        $ex15->order_index = 15;
        R::store($ex15);

        // Exercise 16: Code Exercise
        $ex16 = R::dispense('exercise');
        $ex16->title = "Filter Chain: Category Label";
        $ex16->category = "Filters";
        $ex16->type = "code";
        $ex16->description = "Format product category labels consistently. Output the product type (product.type) in lowercase, and prepend it with 'category-'. Wrap the output inside a span tag with a class of 'meta-tag', like: <span class=\"meta-tag\">category-apparel</span>. Hint: Chain the downcase and prepend filters.";
        $ex16->question_data = json_encode([
            'starter_code' => "<span class=\"meta-tag\">{{ product.type | /* chain filters */ }}</span>",
            'expected_output' => "<span class=\"meta-tag\">category-apparel</span>",
            'solution' => "<span class=\"meta-tag\">{{ product.type | downcase | prepend: 'category-' }}</span>"
        ]);
        $ex16->order_index = 16;
        R::store($ex16);

        // Exercise 17: Code Exercise
        $ex17 = R::dispense('exercise');
        $ex17->title = "Seeded Loop Stock Status";
        $ex17->category = "Loops";
        $ex17->type = "code";
        $ex17->description = "Loop through collections.frontpage.products. For each product, if available (item.available is true), output '<li>X - In Stock</li>' (where X is the product title). Otherwise, output '<li>X - Sold Out</li>'. Keep it formatted inside the surrounding <ul> tags.";
        $ex17->question_data = json_encode([
            'starter_code' => "<ul>\n  {% for item in collections.frontpage.products %}\n    <!-- Enter conditional loop output here -->\n  {% endfor %}\n</ul>",
            'expected_output' => "<ul>\n  \n    <li>Sleek Dark Mug - In Stock</li>\n  \n    <li>Liquid Developer Tee - In Stock</li>\n  \n    <li>Shopify Sticker Pack - Sold Out</li>\n  \n</ul>",
            'solution' => "<ul>\n  {% for item in collections.frontpage.products %}\n    {% if item.available %}\n      <li>{{ item.title }} - In Stock</li>\n    {% else %}\n      <li>{{ item.title }} - Sold Out</li>\n    {% endif %}\n  {% endfor %}\n</ul>"
        ]);
        $ex17->order_index = 17;
        R::store($ex17);

        // Exercise 18: Code Exercise
        $ex18 = R::dispense('exercise');
        $ex18->title = "Cart Items Row Tracker";
        $ex18->category = "Loops";
        $ex18->type = "code";
        $ex18->description = "Display cart line items with their row index numbers. Loop through cart.items. For each item, display: '<div class=\"cart-row\">Item X: Y (Qty: Z)</div>' replacing X with the 1-based loop index (forloop.index), Y with item.title, and Z with item.quantity. Keep them inside the cart-summary wrapper.";
        $ex18->question_data = json_encode([
            'starter_code' => "<div class=\"cart-summary\">\n  {% for item in cart.items %}\n    <!-- Output cart item index tracker -->\n  {% endfor %}\n</div>",
            'expected_output' => "<div class=\"cart-summary\">\n  \n    <div class=\"cart-row\">Item 1: Shopify Retro Cap (Qty: 2)</div>\n  \n    <div class=\"cart-row\">Item 2: Liquid Cheat Sheet (Qty: 1)</div>\n  \n</div>",
            'solution' => "<div class=\"cart-summary\">\n  {% for item in cart.items %}\n    <div class=\"cart-row\">Item {{ forloop.index }}: {{ item.title }} (Qty: {{ item.quantity }})</div>\n  {% endfor %}\n</div>"
        ]);
        $ex18->order_index = 18;
        R::store($ex18);
    }
}

// Execute seeds on load
seed_database();

// -----------------------------------------------------------------------------
// REQUEST CONTROLLER / ACTIONS
// -----------------------------------------------------------------------------

$alert = null;
$route = isset($_GET['page']) ? $_GET['page'] : 'landing';

// Session Status Check for alerts
if (isset($_SESSION['alert_success'])) {
    $alert = ['type' => 'success', 'msg' => $_SESSION['alert_success']];
    unset($_SESSION['alert_success']);
} elseif (isset($_SESSION['alert_danger'])) {
    $alert = ['type' => 'danger', 'msg' => $_SESSION['alert_danger']];
    unset($_SESSION['alert_danger']);
}

// Authentication Actions
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'register') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $_SESSION['alert_danger'] = "Username and password cannot be empty.";
            header("Location: index.php?page=register");
            exit;
        }

        $existing = R::findOne('user', 'username = ?', [$username]);
        if ($existing) {
            $_SESSION['alert_danger'] = "Username already exists.";
            header("Location: index.php?page=register");
            exit;
        }

        $user = R::dispense('user');
        $user->username = $username;
        $user->password_hash = password_hash($password, PASSWORD_BCRYPT);
        $user->role = 'user'; // default role
        R::store($user);

        $_SESSION['alert_success'] = "Registration successful. You can now log in!";
        header("Location: index.php?page=login");
        exit;
    }

    if ($action === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $user = R::findOne('user', 'username = ?', [$username]);
        if ($user && password_verify($password, $user->password_hash)) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['alert_success'] = "Welcome back, " . htmlspecialchars($user->username) . "!";
            header("Location: index.php?page=landing");
            exit;
        } else {
            $_SESSION['alert_danger'] = "Invalid username or password.";
            header("Location: index.php?page=login");
            exit;
        }
    }

    // Submit Exercise Review
    if ($action === 'submit_exercise_review') {
        if (!is_logged_in()) {
            $_SESSION['alert_danger'] = "You must be logged in to submit exercises for review.";
            header("Location: index.php?page=login");
            exit;
        }

        $user = get_logged_user();
        $exId = intval($_POST['exercise_id']);
        $exercise = R::load('exercise', $exId);

        if ($exercise->id) {
            // Check if already completed to prevent duplicate tracking
            $existingProgress = R::findOne('progress', 'user_id = ? AND exercise_id = ?', [$user->id, $exercise->id]);
            if (!$existingProgress) {
                $progress = R::dispense('progress');
                $progress->user_id = $user->id;
                $progress->exercise_id = $exercise->id;
                $progress->completed_at = date('Y-m-d H:i:s');
                $progress->submitted_code = isset($_POST['submitted_code']) ? $_POST['submitted_code'] : '';
                R::store($progress);
            }
            $_SESSION['alert_success'] = "Exercise successfully reviewed and recorded to your profile!";
            header("Location: index.php?page=exercises");
            exit;
        }
    }

    // Admin CRUD Actions
    if (is_admin()) {
        if ($action === 'add_document' || $action === 'edit_document') {
            $docId = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
            $title = trim($_POST['title']);
            $slug = trim($_POST['slug']);
            $category = trim($_POST['category']);
            $parent_slug = trim($_POST['parent_slug']);
            $content = $_POST['content'];
            $order_index = intval($_POST['order_index']);

            if (empty($title) || empty($slug)) {
                $_SESSION['alert_danger'] = "Title and slug are required.";
                header("Location: index.php?page=admin");
                exit;
            }

            $doc = ($action === 'edit_document') ? R::load('document', $docId) : R::dispense('document');
            $doc->title = $title;
            $doc->slug = $slug;
            $doc->category = $category;
            $doc->parent_slug = $parent_slug;
            $doc->content = $content;
            $doc->order_index = $order_index;
            R::store($doc);

            $_SESSION['alert_success'] = "Document successfully updated!";
            header("Location: index.php?page=admin");
            exit;
        }

        if ($action === 'delete_document') {
            $docId = intval($_POST['doc_id']);
            $doc = R::load('document', $docId);
            if ($doc->id) {
                R::trash($doc);
                $_SESSION['alert_success'] = "Document successfully deleted.";
            }
            header("Location: index.php?page=admin");
            exit;
        }

        if ($action === 'add_exercise' || $action === 'edit_exercise') {
            $exId = isset($_POST['ex_id']) ? intval($_POST['ex_id']) : 0;
            $title = trim($_POST['title']);
            $category = trim($_POST['category']);
            $type = $_POST['type'];
            $description = trim($_POST['description']);
            $order_index = intval($_POST['order_index']);

            if (empty($title) || empty($description)) {
                $_SESSION['alert_danger'] = "Title and description are required.";
                header("Location: index.php?page=admin");
                exit;
            }

            $ex = ($action === 'edit_exercise') ? R::load('exercise', $exId) : R::dispense('exercise');
            $ex->title = $title;
            $ex->category = $category;
            $ex->type = $type;
            $ex->description = $description;
            $ex->order_index = $order_index;

            if ($type === 'choice') {
                $options = [
                    trim($_POST['opt_0']),
                    trim($_POST['opt_1']),
                    trim($_POST['opt_2']),
                    trim($_POST['opt_3'])
                ];
                $correct = intval($_POST['correct_option']);
                $ex->question_data = json_encode([
                    'options' => $options,
                    'correct_option' => $correct,
                    'solution' => trim($_POST['choice_solution'])
                ]);
            } else {
                $ex->question_data = json_encode([
                    'starter_code' => $_POST['starter_code'],
                    'expected_output' => $_POST['expected_output'],
                    'solution' => $_POST['code_solution']
                ]);
            }

            R::store($ex);
            $_SESSION['alert_success'] = "Exercise successfully updated!";
            header("Location: index.php?page=admin");
            exit;
        }

        if ($action === 'delete_exercise') {
            $exId = intval($_POST['ex_id']);
            $ex = R::load('exercise', $exId);
            if ($ex->id) {
                R::trash($ex);
                $_SESSION['alert_success'] = "Exercise successfully deleted.";
            }
            header("Location: index.php?page=admin");
            exit;
        }
    }
}

// Log Out endpoint
if ($route === 'logout') {
    session_destroy();
    session_start();
    $_SESSION['alert_success'] = "You have successfully logged out.";
    header("Location: index.php?page=landing");
    exit;
}

// Fetch Completed list if logged in
$completedExercises = [];
if (is_logged_in()) {
    $progressRecords = R::find('progress', 'user_id = ?', [$_SESSION['user_id']]);
    foreach ($progressRecords as $rec) {
        $completedExercises[$rec->exercise_id] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopify Liquid Learner</title>
    <meta name="description" content="An extensive, interactive learning sandboxed platform for Shopify storefront themes and Liquid markup engine.">
    <!-- Load custom responsive dark retro stylesheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Skip link for screen reader navigation compliance -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Header Navigation Section -->
    <header>
        <div class="header-container">
            <div class="logo">
                <a href="index.php?page=landing">LIQUID_ACADEMY</a>
            </div>
            <nav aria-label="Primary Navigation">
                <!-- Keep identical names for similar routing destinations -->
                <a href="index.php?page=landing" class="<?php echo $route === 'landing' ? 'active' : ''; ?>">Landing</a>
                <a href="index.php?page=docs" class="<?php echo $route === 'docs' ? 'active' : ''; ?>">Documentation</a>
                <a href="index.php?page=exercises" class="<?php echo $route === 'exercises' ? 'active' : ''; ?>">Exercises</a>
                
                <?php if (is_admin()): ?>
                    <a href="index.php?page=admin" class="<?php echo $route === 'admin' ? 'active' : ''; ?>">Admin Panel</a>
                <?php endif; ?>

                <?php if (is_logged_in()): ?>
                    <div class="user-status">
                        <span>[<?php echo htmlspecialchars(get_logged_user()->username); ?>]</span>
                        <a href="index.php?page=logout">Log Out</a>
                    </div>
                <?php else: ?>
                    <a href="index.php?page=login" class="<?php echo $route === 'login' ? 'active' : ''; ?>">Log In</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content Body -->
    <main id="main-content">
        
        <!-- Banners for System Notifications & Errors -->
        <?php if ($alert): ?>
            <div class="alert alert-<?php echo $alert['type']; ?>" role="alert">
                <strong><?php echo $alert['type'] === 'success' ? 'SYSTEM SUCCESS:' : 'SYSTEM ERROR:'; ?></strong> <?php echo htmlspecialchars($alert['msg']); ?>
            </div>
        <?php endif; ?>

        <?php
        // ---------------------------------------------------------------------
        // VIEW: LANDING
        // ---------------------------------------------------------------------
        if ($route === 'landing'):
        ?>
            <section class="hero">
                <h1>Learn Shopify Theme Development</h1>
                <p>Master the Shopify Liquid templating engine with comprehensive interactive code environments, structured documentation, and guided multiple choice exercises.</p>
                <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                    <a href="index.php?page=docs" class="btn">Explore Documentation</a>
                    <a href="index.php?page=exercises" class="btn">Solve Exercises</a>
                </div>
            </section>

            <section class="feature-grid">
                <div class="feature-card">
                    <h2>📚 Structured Documentation</h2>
                    <p>Dive deep into our hierarchical files, exploring tags, filters, objects, variables, and loop iteration guidelines. Supported by code-highlighted IDE representations.</p>
                    <a href="index.php?page=docs" class="btn" style="width:fit-content; margin-top:auto;">Explore Documentation</a>
                </div>
                <div class="feature-card">
                    <h2>⚡ Live Liquid Simulator</h2>
                    <p>Our custom-built interpreter executes Liquid blocks in real-time. Code loops, logic conditions, and modifications, previewing outputs instantly.</p>
                    <a href="index.php?page=exercises" class="btn" style="width:fit-content; margin-top:auto;">Solve Exercises</a>
                </div>
                <div class="feature-card">
                    <h2>🛡️ Administrative Management</h2>
                    <p>Administrators have absolute authorization to expand lessons by dynamically editing files, publishing new modules, and creating diverse test scenarios.</p>
                    <?php if (is_admin()): ?>
                        <a href="index.php?page=admin" class="btn" style="width:fit-content; margin-top:auto;">Admin Panel</a>
                    <?php else: ?>
                        <a href="index.php?page=login" class="btn" style="width:fit-content; margin-top:auto;">Log In</a>
                    <?php endif; ?>
                </div>
            </section>

        <?php
        // ---------------------------------------------------------------------
        // VIEW: DOCUMENTATION
        // ---------------------------------------------------------------------
        elseif ($route === 'docs'):
            // Fetch parent documents
            $parentDocs = R::find('document', "parent_slug = '' OR parent_slug IS NULL ORDER BY order_index ASC");
            
            // Get currently active document
            $currentSlug = isset($_GET['doc']) ? trim($_GET['doc']) : 'overview';
            $currentDoc = R::findOne('document', 'slug = ?', [$currentSlug]);
            
            // If active doc not found, default to overview
            if (!$currentDoc) {
                $currentDoc = R::findOne('document', 'slug = ?', ['overview']);
            }
        ?>
            <div class="docs-container">
                <aside class="docs-sidebar" aria-label="Documentation Tree">
                    <h3>Lessons</h3>
                    <div class="docs-nav">
                        <?php foreach ($parentDocs as $pDoc): ?>
                            <a href="index.php?page=docs&doc=<?php echo urlencode($pDoc->slug); ?>" 
                               class="docs-nav-item <?php echo $currentDoc->slug === $pDoc->slug ? 'active' : ''; ?>">
                               <?php echo htmlspecialchars($pDoc->title); ?>
                            </a>
                            
                            <!-- Fetch children/nested pages -->
                            <?php 
                            $childDocs = R::find('document', "parent_slug = ? ORDER BY order_index ASC", [$pDoc->slug]);
                            if (count($childDocs) > 0): 
                            ?>
                                <div class="docs-nav-sub">
                                    <?php foreach ($childDocs as $cDoc): ?>
                                        <a href="index.php?page=docs&doc=<?php echo urlencode($cDoc->slug); ?>" 
                                           class="docs-nav-sub-item <?php echo $currentDoc->slug === $cDoc->slug ? 'active' : ''; ?>">
                                           &rsaquo; <?php echo htmlspecialchars($cDoc->title); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <article class="docs-content">
                    <?php if ($currentDoc): ?>
                        <div class="markdown-rendered">
                            <?php echo parseMarkdown($currentDoc->content); ?>
                        </div>
                    <?php else: ?>
                        <h1>Lesson Not Found</h1>
                        <p>The requested lesson could not be loaded. Please select another module from the sidebar.</p>
                    <?php endif; ?>
                </article>
            </div>

        <?php
        // ---------------------------------------------------------------------
        // VIEW: EXERCISES
        // ---------------------------------------------------------------------
        elseif ($route === 'exercises'):
            $selectedExId = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $allExercises = R::find('exercise', 'ORDER BY order_index ASC');
            
            $activeEx = null;
            if ($selectedExId > 0) {
                $activeEx = R::load('exercise', $selectedExId);
            }
            
            // Render list if no exercise selected
            if (!$activeEx || !$activeEx->id):
        ?>
                <h1>Liquid Learning Exercises</h1>
                <p>Sharpen your Shopify theme development abilities. Solve questions below to review your answers.</p>

                <?php if (!is_logged_in()): ?>
                    <div class="alert alert-info">
                        <strong>PRO-TIP:</strong> You can practice and test exercises freely. However, to submit solutions for permanent tracking, please <a href="index.php?page=login">Log In</a>.
                    </div>
                <?php endif; ?>

                <div class="exercise-list">
                    <?php foreach ($allExercises as $ex): 
                        $isComp = isset($completedExercises[$ex->id]);
                    ?>
                        <div class="exercise-card <?php echo $isComp ? 'completed' : ''; ?>">
                            <div class="exercise-info">
                                <h3>
                                    <?php echo htmlspecialchars($ex->title); ?>
                                    <?php if ($isComp): ?>
                                        <span class="tag-badge completed-badge">COMPLETED</span>
                                    <?php else: ?>
                                        <span class="tag-badge">PENDING</span>
                                    <?php endif; ?>
                                </h3>
                                <p style="margin: 0; font-size: 0.9rem;"><?php echo htmlspecialchars($ex->description); ?></p>
                            </div>
                            <div>
                                <span class="tag-badge" style="margin-right:1rem;"><?php echo $ex->type === 'choice' ? 'Multiple Choice' : 'Code Playground'; ?></span>
                                <a href="index.php?page=exercises&id=<?php echo $ex->id; ?>" class="btn">Solve Exercises</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

        <?php 
            else: 
                // Active exercise workspace
                $qData = json_decode($activeEx->question_data, true);
        ?>
                <div style="margin-bottom: 1.5rem;">
                    <a href="index.php?page=exercises" class="btn btn-secondary">&larr; Back to Exercises</a>
                </div>

                <h1>Exercise: <?php echo htmlspecialchars($activeEx->title); ?></h1>
                
                <div class="exercise-container <?php echo $activeEx->type === 'code' ? 'code-exercise-layout' : ''; ?>">
                    
                    <!-- Left: Description and Inputs -->
                    <div class="exercise-panel">
                        <h2>Description</h2>
                        <p><?php echo htmlspecialchars($activeEx->description); ?></p>
                        
                        <?php if ($activeEx->type === 'choice'): ?>
                            <!-- MULTIPLE CHOICE WORKSPACE -->
                            <form id="choice-form" onsubmit="evaluateChoice(event)">
                                <div class="options-list">
                                    <?php foreach ($qData['options'] as $idx => $opt): ?>
                                        <label class="option-item" id="label-opt-<?php echo $idx; ?>">
                                            <input type="radio" name="mc_option" value="<?php echo $idx; ?>" required onclick="selectOptionStyle(<?php echo $idx; ?>)">
                                            <span><?php echo htmlspecialchars($opt); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem;">
                                    <button type="submit" id="eval-btn">Evaluate Answer</button>
                                    <button type="button" class="btn btn-secondary" id="solution-btn" style="display:none;" onclick="revealSolution()">Reveal Solution</button>
                                    <button type="button" class="btn" id="retry-btn" style="display:none;" onclick="resetChoice()">Try Again</button>
                                </div>
                            </form>

                            <div id="choice-feedback" style="margin-top:1.5rem;"></div>

                            <!-- Permanent Progress Submission form for logged in users -->
                            <?php if (is_logged_in()): ?>
                                <form id="review-submit-form" action="index.php" method="POST" style="display:none; margin-top: 1rem;">
                                    <input type="hidden" name="action" value="submit_exercise_review">
                                    <input type="hidden" name="exercise_id" value="<?php echo $activeEx->id; ?>">
                                    <button type="submit">Submit for Review</button>
                                </form>
                            <?php else: ?>
                                <div id="login-notice" class="alert alert-info" style="display:none; margin-top: 1rem;">
                                    Please <a href="index.php?page=login">Log In</a> to save this completion on your profile!
                                </div>
                            <?php endif; ?>

                            <script>
                                const correctIndex = <?php echo intval($qData['correct_option']); ?>;
                                const explanation = <?php echo json_encode($qData['solution']); ?>;

                                function selectOptionStyle(idx) {
                                    document.querySelectorAll('.option-item').forEach(el => el.classList.remove('selected'));
                                    document.getElementById('label-opt-' + idx).classList.add('selected');
                                }

                                function evaluateChoice(event) {
                                    event.preventDefault();
                                    const selected = document.querySelector('input[name="mc_option"]:checked');
                                    if (!selected) return;

                                    const selectedIdx = parseInt(selected.value);
                                    const feedbackEl = document.getElementById('choice-feedback');
                                    
                                    if (selectedIdx === correctIndex) {
                                        feedbackEl.innerHTML = `<div class="alert alert-success"><strong>CORRECT!</strong> Excellent work. ${explanation}</div>`;
                                        document.getElementById('eval-btn').style.display = 'none';
                                        document.getElementById('retry-btn').style.display = 'none';
                                        document.getElementById('solution-btn').style.display = 'none';
                                        
                                        // Show review submission form if logged in
                                        if (document.getElementById('review-submit-form')) {
                                            document.getElementById('review-submit-form').style.display = 'block';
                                        }
                                        if (document.getElementById('login-notice')) {
                                            document.getElementById('login-notice').style.display = 'block';
                                        }
                                    } else {
                                        feedbackEl.innerHTML = `<div class="alert alert-danger"><strong>INCORRECT:</strong> That's not the right option. Read carefully and try again.</div>`;
                                        document.getElementById('solution-btn').style.display = 'inline-block';
                                        document.getElementById('retry-btn').style.display = 'inline-block';
                                    }
                                }

                                function revealSolution() {
                                    const feedbackEl = document.getElementById('choice-feedback');
                                    feedbackEl.innerHTML = `<div class="alert alert-info"><strong>SOLUTION DETAILS:</strong> The correct option was: <em>${<?php echo json_encode($qData['options'][$qData['correct_option']]); ?>}</em>.<br><br>${explanation}</div>`;
                                }

                                function resetChoice() {
                                    document.getElementById('choice-form').reset();
                                    document.querySelectorAll('.option-item').forEach(el => el.classList.remove('selected'));
                                    document.getElementById('choice-feedback').innerHTML = '';
                                    document.getElementById('eval-btn').style.display = 'inline-block';
                                    document.getElementById('solution-btn').style.display = 'none';
                                    document.getElementById('retry-btn').style.display = 'none';
                                }
                            </script>

                        <?php else: ?>
                            <!-- CODE EXERCISE PLAYGROUND WORKSPACE -->
                            <div class="code-editor-header">
                                <span>liquid_editor.html</span>
                                <span>Liquid Language Enabled</span>
                            </div>
                            <textarea id="code-writer" class="code-textarea" autocomplete="off" spellcheck="false" oninput="compileRealTime()"><?php echo htmlspecialchars($qData['starter_code']); ?></textarea>

                            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem;">
                                <button type="button" onclick="evaluateCode()">Evaluate Code</button>
                                <button type="button" class="btn btn-secondary" id="code-solution-btn" style="display:none;" onclick="revealCodeSolution()">Reveal Solution</button>
                                <button type="button" class="btn" id="code-retry-btn" style="display:none;" onclick="resetCodeSandbox()">Try Again</button>
                            </div>

                            <div id="code-feedback" style="margin-top:1.5rem;"></div>

                            <!-- Permanent Progress Submission form for logged in users -->
                            <?php if (is_logged_in()): ?>
                                <form id="code-review-submit-form" action="index.php" method="POST" style="display:none; margin-top: 1rem;">
                                    <input type="hidden" name="action" value="submit_exercise_review">
                                    <input type="hidden" name="exercise_id" value="<?php echo $activeEx->id; ?>">
                                    <input type="hidden" name="submitted_code" id="submitted-code-input">
                                    <button type="submit">Submit for Review</button>
                                </form>
                            <?php else: ?>
                                <div id="code-login-notice" class="alert alert-info" style="display:none; margin-top: 1rem;">
                                    Please <a href="index.php?page=login">Log In</a> to save this completion on your profile!
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Right Panel: Code Sandbox Live Preview (Only for Code exercises) -->
                    <?php if ($activeEx->type === 'code'): ?>
                        <div class="exercise-panel">
                            <h2>Sandbox Output (Real-time)</h2>
                            <p style="font-size:0.9rem;">Below is the live simulated sandbox rendering your Liquid variables against predefined Shopify stores data.</p>
                            
                            <div class="result-header">Preview Sandbox Rendered Output</div>
                            <div class="result-panel">
                                <div id="live-rendered-preview" class="result-output"></div>
                            </div>
                        </div>

                        <!-- Attach Simulator Javascript dynamically -->
                        <script src="liquid-simulator.js"></script>
                        <script>
                            const simulator = new LiquidSimulator();
                            const expectedOutputStr = <?php echo json_encode($qData['expected_output']); ?>;
                            const codeSolutionStr = <?php echo json_encode($qData['solution']); ?>;

                            // Normalize output string to match values accurately
                            function normalizeOutput(str) {
                                return str.replace(/\s+/g, ' ').trim();
                            }

                            function compileRealTime() {
                                const input = document.getElementById('code-writer').value;
                                try {
                                    const rendered = simulator.render(input);
                                    document.getElementById('live-rendered-preview').innerHTML = rendered;
                                } catch (e) {
                                    document.getElementById('live-rendered-preview').innerHTML = `<span style="color:#ff3333;">Interpreter Error: ${e.message}</span>`;
                                }
                            }

                            function escapeHTML(text) {
                                return text
                                    .replace(/&/g, "&amp;")
                                    .replace(/</g, "&lt;")
                                    .replace(/>/g, "&gt;")
                                    .replace(/"/g, "&quot;")
                                    .replace(/'/g, "&#039;");
                            }

                            function evaluateCode() {
                                const input = document.getElementById('code-writer').value;
                                const rendered = simulator.render(input);

                                const normalizedRendered = normalizeOutput(rendered);
                                const normalizedExpected = normalizeOutput(expectedOutputStr);
                                const feedbackEl = document.getElementById('code-feedback');

                                if (normalizedRendered === normalizedExpected) {
                                    feedbackEl.innerHTML = `<div class="alert alert-success"><strong>EXCELLENT WORK:</strong> Your Liquid rendering successfully generated the target HTML output structure!</div>`;
                                    document.getElementById('code-solution-btn').style.display = 'none';
                                    document.getElementById('code-retry-btn').style.display = 'none';

                                    if (document.getElementById('code-review-submit-form')) {
                                        document.getElementById('submitted-code-input').value = input;
                                        document.getElementById('code-review-submit-form').style.display = 'block';
                                    }
                                    if (document.getElementById('code-login-notice')) {
                                        document.getElementById('code-login-notice').style.display = 'block';
                                    }
                                } else {
                                    feedbackEl.innerHTML = `<div class="alert alert-danger"><strong>UNMATCHED OUTPUT:</strong> Rendered output did not match expected structure. Keep tuning and review variables.</div>`;
                                    document.getElementById('code-solution-btn').style.display = 'inline-block';
                                    document.getElementById('code-retry-btn').style.display = 'inline-block';
                                }
                            }

                            function revealCodeSolution() {
                                const feedbackEl = document.getElementById('code-feedback');
                                feedbackEl.innerHTML = `<div class="alert alert-info"><strong>EXPECTED SOLUTION SNIPPET:</strong><br><pre><code>${escapeHTML(codeSolutionStr)}</code></pre></div>`;
                            }

                            function resetCodeSandbox() {
                                document.getElementById('code-writer').value = <?php echo json_encode($qData['starter_code']); ?>;
                                compileRealTime();
                                document.getElementById('code-feedback').innerHTML = '';
                                document.getElementById('code-solution-btn').style.display = 'none';
                                document.getElementById('code-retry-btn').style.display = 'none';
                            }

                            // Trigger compiler on page ready
                            window.addEventListener('DOMContentLoaded', () => {
                                compileRealTime();
                            });
                        </script>
                    <?php endif; ?>
                </div>
        <?php 
            endif;
        ?>

        <?php
        // ---------------------------------------------------------------------
        // VIEW: ADMIN
        // ---------------------------------------------------------------------
        elseif ($route === 'admin'):
            if (!is_admin()) {
                echo "<h1>Unauthorized Access</h1><p>You must be an administrator to view this control page.</p>";
            } else {
                $allDocs = R::find('document', 'ORDER BY category, order_index ASC');
                $allExs = R::find('exercise', 'ORDER BY order_index ASC');

                // Get single doc for edit action
                $editDoc = null;
                if (isset($_GET['edit_doc_id'])) {
                    $editDoc = R::load('document', intval($_GET['edit_doc_id']));
                }

                // Get single exercise for edit action
                $editEx = null;
                if (isset($_GET['edit_ex_id'])) {
                    $editEx = R::load('exercise', intval($_GET['edit_ex_id']));
                }
        ?>
                <h1>Administrative Control Dashboard</h1>
                <p>Welcome, Supervisor. Publish dynamic lessons, update course documentation, and expand exercises sandbox database.</p>

                <div class="admin-grid">
                    <aside class="admin-sidebar" aria-label="Admin Navigation">
                        <a href="index.php?page=admin#manage-docs" class="btn admin-sidebar-btn">Lessons / Pages</a>
                        <a href="index.php?page=admin#manage-exercises" class="btn admin-sidebar-btn">Platform Exercises</a>
                        <a href="index.php?page=admin&action=new_doc#doc-form" class="btn admin-sidebar-btn">+ New Lesson</a>
                        <a href="index.php?page=admin&action=new_ex#ex-form" class="btn admin-sidebar-btn">+ New Exercise</a>
                    </aside>

                    <div>
                        <!-- Section: Manage Documentation -->
                        <section id="manage-docs" style="margin-bottom: 3rem;">
                            <h2>Manage Lessons & Pages</h2>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Title</th>
                                        <th>Slug</th>
                                        <th>Parent Slug</th>
                                        <th>Order</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allDocs as $doc): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($doc->category); ?></td>
                                            <td><?php echo htmlspecialchars($doc->title); ?></td>
                                            <td><code><?php echo htmlspecialchars($doc->slug); ?></code></td>
                                            <td><code><?php echo htmlspecialchars($doc->parent_slug); ?></code></td>
                                            <td><?php echo $doc->order_index; ?></td>
                                            <td class="admin-actions">
                                                <a href="index.php?page=admin&edit_doc_id=<?php echo $doc->id; ?>#doc-form" class="btn btn-secondary" style="padding:0.25rem 0.5rem; font-size:0.8rem;">Edit</a>
                                                <form action="index.php?page=admin" method="POST" onsubmit="return confirm('Are you sure you want to delete this document page?');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_document">
                                                    <input type="hidden" name="doc_id" value="<?php echo $doc->id; ?>">
                                                    <button type="submit" class="btn btn-secondary" style="padding:0.25rem 0.5rem; font-size:0.8rem; border-color:var(--error-color); color:var(--error-color);">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Form: Create / Edit Document -->
                            <?php if (isset($_GET['action']) && $_GET['action'] === 'new_doc' || $editDoc): ?>
                                <div class="auth-container" id="doc-form" style="max-width:100%; margin: 2rem 0;">
                                    <h2><?php echo $editDoc ? 'Edit Page: ' . htmlspecialchars($editDoc->title) : 'Create New Lesson/Page'; ?></h2>
                                    <form action="index.php?page=admin" method="POST">
                                        <input type="hidden" name="action" value="<?php echo $editDoc ? 'edit_document' : 'add_document'; ?>">
                                        <?php if ($editDoc): ?>
                                            <input type="hidden" name="doc_id" value="<?php echo $editDoc->id; ?>">
                                        <?php endif; ?>

                                        <div class="form-group">
                                            <label for="doc-title">Page Title</label>
                                            <input type="text" id="doc-title" name="title" value="<?php echo $editDoc ? htmlspecialchars($editDoc->title) : ''; ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="doc-slug">Slug (Unique identifier used in URL)</label>
                                            <input type="text" id="doc-slug" name="slug" value="<?php echo $editDoc ? htmlspecialchars($editDoc->slug) : ''; ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="doc-category">Category Group</label>
                                            <input type="text" id="doc-category" name="category" value="<?php echo $editDoc ? htmlspecialchars($editDoc->category) : ''; ?>" placeholder="e.g. Getting Started" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="doc-parent">Parent Page Slug (Leave blank for top-level pages)</label>
                                            <input type="text" id="doc-parent" name="parent_slug" value="<?php echo $editDoc ? htmlspecialchars($editDoc->parent_slug) : ''; ?>" placeholder="e.g. overview">
                                        </div>

                                        <div class="form-group">
                                            <label for="doc-order">Sort Order Index</label>
                                            <input type="number" id="doc-order" name="order_index" value="<?php echo $editDoc ? intval($editDoc->order_index) : 1; ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="doc-content">Content Markdown Format</label>
                                            <textarea id="doc-content" name="content" rows="12" required><?php echo $editDoc ? htmlspecialchars($editDoc->content) : ''; ?></textarea>
                                        </div>

                                        <div style="display:flex; gap:1rem;">
                                            <button type="submit">Save Lesson</button>
                                            <a href="index.php?page=admin" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </section>

                        <!-- Section: Manage Exercises -->
                        <section id="manage-exercises" style="margin-bottom: 3rem;">
                            <h2>Manage Platform Exercises</h2>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allExs as $ex): ?>
                                        <tr>
                                            <td><?php echo $ex->order_index; ?></td>
                                            <td><?php echo htmlspecialchars($ex->title); ?></td>
                                            <td><?php echo htmlspecialchars($ex->category); ?></td>
                                            <td><code><?php echo $ex->type === 'choice' ? 'Choice' : 'Code Editor'; ?></code></td>
                                            <td class="admin-actions">
                                                <a href="index.php?page=admin&edit_ex_id=<?php echo $ex->id; ?>#ex-form" class="btn btn-secondary" style="padding:0.25rem 0.5rem; font-size:0.8rem;">Edit</a>
                                                <form action="index.php?page=admin" method="POST" onsubmit="return confirm('Are you sure you want to delete this exercise?');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_exercise">
                                                    <input type="hidden" name="ex_id" value="<?php echo $ex->id; ?>">
                                                    <button type="submit" class="btn btn-secondary" style="padding:0.25rem 0.5rem; font-size:0.8rem; border-color:var(--error-color); color:var(--error-color);">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Form: Create / Edit Exercise (Multiple Choice vs Code formulary) -->
                            <?php 
                            if (isset($_GET['action']) && $_GET['action'] === 'new_ex' || $editEx): 
                                $exData = [];
                                if ($editEx) {
                                    $exData = json_decode($editEx->question_data, true);
                                }
                                $exType = $editEx ? $editEx->type : (isset($_GET['type']) ? $_GET['type'] : 'choice');
                            ?>
                                <div class="auth-container" id="ex-form" style="max-width:100%; margin: 2rem 0;">
                                    <h2><?php echo $editEx ? 'Edit Exercise: ' . htmlspecialchars($editEx->title) : 'Create New Practice Exercise'; ?></h2>
                                    
                                    <!-- Dynamic form switcher to fulfill separate formulary layouts requirement -->
                                    <?php if (!$editEx): ?>
                                        <div style="margin-bottom:1.5rem; background:var(--bg-tertiary); padding:1rem; border:1px solid var(--border-color);">
                                            <label style="margin-bottom:0.5rem; display:block;">Select Exercise Type Formulary:</label>
                                            <div style="display:flex; gap:1rem;">
                                                <a href="index.php?page=admin&action=new_ex&type=choice#ex-form" class="btn <?php echo $exType === 'choice' ? '' : 'btn-secondary'; ?>">Multiple Choice Quiz</a>
                                                <a href="index.php?page=admin&action=new_ex&type=code#ex-form" class="btn <?php echo $exType === 'code' ? '' : 'btn-secondary'; ?>">Liquid Code Writer</a>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <form action="index.php?page=admin" method="POST">
                                        <input type="hidden" name="action" value="<?php echo $editEx ? 'edit_exercise' : 'add_exercise'; ?>">
                                        <input type="hidden" name="type" value="<?php echo $exType; ?>">
                                        <?php if ($editEx): ?>
                                            <input type="hidden" name="ex_id" value="<?php echo $editEx->id; ?>">
                                        <?php endif; ?>

                                        <div class="form-group">
                                            <label for="ex-title">Exercise Title</label>
                                            <input type="text" id="ex-title" name="title" value="<?php echo $editEx ? htmlspecialchars($editEx->title) : ''; ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="ex-category">Category / Topic</label>
                                            <input type="text" id="ex-category" name="category" value="<?php echo $editEx ? htmlspecialchars($editEx->category) : ''; ?>" placeholder="e.g. Basic Syntax" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="ex-order">Sort Order Index</label>
                                            <input type="number" id="ex-order" name="order_index" value="<?php echo $editEx ? intval($editEx->order_index) : 1; ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="ex-desc">Question Description</label>
                                            <textarea id="ex-desc" name="description" rows="4" required><?php echo $editEx ? htmlspecialchars($editEx->description) : ''; ?></textarea>
                                        </div>

                                        <!-- Formulary Option 1: Multiple Choice Quiz -->
                                        <?php if ($exType === 'choice'): ?>
                                            <div style="background-color: var(--bg-tertiary); padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                                                <h3>Quiz Options Formulary</h3>
                                                
                                                <div class="form-group">
                                                    <label for="opt-0">Option A</label>
                                                    <input type="text" id="opt-0" name="opt_0" value="<?php echo $editEx ? htmlspecialchars($exData['options'][0]) : ''; ?>" required>
                                                </div>

                                                <div class="form-group">
                                                    <label for="opt-1">Option B</label>
                                                    <input type="text" id="opt-1" name="opt_1" value="<?php echo $editEx ? htmlspecialchars($exData['options'][1]) : ''; ?>" required>
                                                </div>

                                                <div class="form-group">
                                                    <label for="opt-2">Option C</label>
                                                    <input type="text" id="opt-2" name="opt_2" value="<?php echo $editEx ? htmlspecialchars($exData['options'][2]) : ''; ?>" required>
                                                </div>

                                                <div class="form-group">
                                                    <label for="opt-3">Option D</label>
                                                    <input type="text" id="opt-3" name="opt_3" value="<?php echo $editEx ? htmlspecialchars($exData['options'][3]) : ''; ?>" required>
                                                </div>

                                                <div class="form-group">
                                                    <label for="correct-opt">Correct Option Index</label>
                                                    <select id="correct-opt" name="correct_option">
                                                        <option value="0" <?php echo ($editEx && $exData['correct_option'] == 0) ? 'selected' : ''; ?>>Option A</option>
                                                        <option value="1" <?php echo ($editEx && $exData['correct_option'] == 1) ? 'selected' : ''; ?>>Option B</option>
                                                        <option value="2" <?php echo ($editEx && $exData['correct_option'] == 2) ? 'selected' : ''; ?>>Option C</option>
                                                        <option value="3" <?php echo ($editEx && $exData['correct_option'] == 3) ? 'selected' : ''; ?>>Option D</option>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label for="choice-sol">Solution Explanation Details</label>
                                                    <textarea id="choice-sol" name="choice_solution" rows="3"><?php echo $editEx ? htmlspecialchars($exData['solution']) : ''; ?></textarea>
                                                </div>
                                            </div>

                                        <!-- Formulary Option 2: Liquid Code Sandbox Writer -->
                                        <?php else: ?>
                                            <div style="background-color: var(--bg-tertiary); padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                                                <h3>Code Playground Config Formulary</h3>

                                                <div class="form-group">
                                                    <label for="start-code">Starter Templates/Code</label>
                                                    <textarea id="start-code" name="starter_code" rows="5" required><?php echo $editEx ? htmlspecialchars($exData['starter_code']) : ''; ?></textarea>
                                                </div>

                                                <div class="form-group">
                                                    <label for="exp-output">Target / Expected Render Output HTML</label>
                                                    <textarea id="exp-output" name="expected_output" rows="5" required><?php echo $editEx ? htmlspecialchars($exData['expected_output']) : ''; ?></textarea>
                                                </div>

                                                <div class="form-group">
                                                    <label for="code-sol">Correct Solution / Answer Snippet</label>
                                                    <textarea id="code-sol" name="code_solution" rows="5" required><?php echo $editEx ? htmlspecialchars($exData['solution']) : ''; ?></textarea>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div style="display:flex; gap:1rem;">
                                            <button type="submit">Save Exercise</button>
                                            <a href="index.php?page=admin" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                </div>
        <?php
            }
        // ---------------------------------------------------------------------
        // VIEW: LOGIN
        // ---------------------------------------------------------------------
        elseif ($route === 'login'):
        ?>
            <div class="auth-container">
                <h1>Log In to Sandbox</h1>
                <p>Welcome back, Learner. Input your credentials below.</p>

                <!-- Mock Account notices as requested -->
                <div class="auth-notice">
                    <strong>📢 MOCK MIGRATION ACCOUNT:</strong><br>
                    To test administrative pages and lesson creator dashboards, log in using:
                    <br>Username: <code>Admin</code>
                    <br>Password: <code>Admin123</code>
                </div>

                <form action="index.php?page=login" method="POST">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label for="login-username">Username</label>
                        <input type="text" id="login-username" name="username" required autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input type="password" id="login-password" name="password" required autocomplete="current-password">
                    </div>

                    <button type="submit" style="width:100%; margin-bottom:1.5rem;">Log In</button>
                    <p style="text-align:center; font-size:0.9rem;">
                        No account yet? <a href="index.php?page=register">Create Account</a>
                    </p>
                </form>
            </div>

        <?php
        // ---------------------------------------------------------------------
        // VIEW: REGISTER
        // ---------------------------------------------------------------------
        elseif ($route === 'register'):
        ?>
            <div class="auth-container">
                <h1>Create Sandbox Account</h1>
                <p>Sign up to track exercise completion metrics and submit your templates for review.</p>

                <form action="index.php?page=register" method="POST">
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label for="reg-username">Username</label>
                        <input type="text" id="reg-username" name="username" required autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="reg-password">Password</label>
                        <input type="password" id="reg-password" name="password" required autocomplete="new-password">
                    </div>

                    <button type="submit" style="width:100%; margin-bottom:1.5rem;">Create Account</button>
                    <p style="text-align:center; font-size:0.9rem;">
                        Already registered? <a href="index.php?page=login">Log In</a>
                    </p>
                </form>
            </div>
        <?php
        endif;
        ?>

    </main>

    <!-- Site Footer Section -->
    <footer>
        <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                &copy; <?php echo date('Y'); ?> Shopify Liquid Learner Academy. Not affiliated with Shopify Inc.
            </div>
            <div>
                <!-- Consistent navigation names as requested -->
                <a href="index.php?page=landing" style="margin-left: 1rem;">Landing</a>
                <a href="index.php?page=docs" style="margin-left: 1rem;">Documentation</a>
                <a href="index.php?page=exercises" style="margin-left: 1rem;">Exercises</a>
            </div>
        </div>
    </footer>

</body>
</html>