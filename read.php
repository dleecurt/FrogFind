<?php
require_once('vendor/autoload.php');
require_once('helpers.php');

$article_url = "";
$article_html = "";
$readable_article = "";
$error_text = "";

// List of Content-Types that we know we can (try to) parse. 
// Anything else will get piped through directly, if possible.
$compatible_content_types = [
    "text/html",
    "text/plain"
];

// The maximum allowed filesize for proxy download passthroughs. 
// Any file larger than this will instead show an error message, with
// a direct link to the file.
$proxy_download_max_filesize = 8000000; // ~ 8Mb

if( isset( $_GET['a'] ) ) {
    $article_url = $_GET["a"];
} else {
    echo "What do you think you're doing... >:(";
    exit();
}

// Accept addresses typed without "http://", and refuse non-web schemes
$normalized_url = frog_normalize_url($article_url);
if ($normalized_url === null) {
    echo("That's not a web page :( <br>");
    echo('Try an address like <b>example.com</b> or <b>http://example.com/page.html</b>. <a href="/">Back to FrogFind</a>');
    die();
}
$article_url = $normalized_url;

$url = parse_url($article_url);

// Attempt to figure out what the requested URL content-type may be
$headers = @get_headers($article_url, true, frog_context('HEAD'));
$content_type = frog_header($headers, 'Content-Type');
$content_length = frog_header($headers, 'Content-Length');

if (!$content_type) {
    $error_text .=  "Failed to get the article, its server did not return expected details :( <br>";
}
else {
    // Compare only the media type, e.g. "text/html" from "text/html; charset=utf-8"
    $media_type = strtolower(trim(explode(';', $content_type)[0]));

    // Attempt to handle downloads or other mime-types by passing proxying them through.
    if (!in_array($media_type, $compatible_content_types)) {
        $filesize = $content_length;

        // Check if the linked file isn't too large for us to proxy (or of unknown size).
        if (!is_numeric($filesize) || $filesize > $proxy_download_max_filesize) {
            echo 'Failed to proxy file download, it\'s too large or its size is unknown. :( <br>';
            echo 'You can try downloading the file directly: ' . h($article_url);
            die();
        }
        else {
            $filename = basename((string) ($url['path'] ?? ''));

            // If no filename can be deduced from the URL, set a placeholder filename
            if (!$filename) {
                $filename = "download";
            }
            $filename = str_replace(['"', "\r", "\n"], '', $filename);

            // Set the content headers based on the file we're proxying through.
            header('Content-Type: ' . $content_type);
            header('Content-Length: ' . $filesize);
            // Set the content-disposition to encourage the browser to download the file.
            header('Content-Disposition: attachment; filename="'. $filename . '"');

            // Use readfile 
            readfile($article_url);
            die();
        }
    }
}

use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;

require_once('FrogReadability.php');

if(!$article_html = @file_get_contents($article_url, false, frog_context())) {
    $error_text .=  "Failed to get the article :( <br>";
}

// Relative links are resolved against the page's real address: the full URL
// (scheme, port, path), after following any redirects.
$base_url = frog_final_url($article_url, isset($http_response_header) ? $http_response_header : []);

$configuration = new Configuration();
$configuration
    ->setArticleByLine(false)
    ->setFixRelativeURLs(true)
    ->setOriginalURL($base_url);

$readability = new FrogReadability($configuration);

try {
    $readability->parse($article_html);
    $readable_article = strip_tags($readability->getContent(), '<a><ol><ul><li><br><p><small><font><b><strong><i><em><blockquote><h1><h2><h3><h4><h5><h6>');
    $readable_article = str_replace( 'strong>', 'b>', $readable_article ); //change <strong> to <b>
    $readable_article = str_replace( 'em>', 'i>', $readable_article ); //change <em> to <i>
    
    $readable_article = clean_str($readable_article);
    //route links through proxy, URL-encoded so query strings (&, #) survive
    $readable_article = preg_replace_callback('/href="(https?:[^"]*)"/i', function ($m) {
        return 'href="/read.php?a=' . urlencode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')) . '"';
    }, $readable_article);
    
} catch (ParseException $e) {
    $error_text .= 'Sorry! ' . $e->getMessage() . '<br>';
}

//replace chars that old machines probably can't handle
function clean_str($str) {
    $str = str_replace( "‘", "'", $str );    
    $str = str_replace( "’", "'", $str );  
    $str = str_replace( "“", '"', $str ); 
    $str = str_replace( "”", '"', $str );
    $str = str_replace( "–", '-', $str );

    return $str;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 2.0//EN">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
 
 <html>
 <head>
     <title><?php echo h($readability->getTitle());?></title>
 </head>
 <body>
    <p>
        <form action="/read.php" method="get">
        <a href="/">Back to <b><font color="#008000">Frog</font><font color="000000">Find!</font></a></b> | Browsing URL: <input type="text" size="38" name="a" value="<?php echo h($article_url) ?>">
        <input type="submit" value="Go!">
        </form>
    </p>
    <hr>
    <h1><?php echo h(clean_str((string) $readability->getTitle()));?></h1>
    <p> <?php
        $img_num = 0;
        $imgline_html = "View page images:";
        foreach ($readability->getImages() as $image_url):
            //we can only do png and jpg
            if (frog_image_type($image_url)) {
                $img_num++;
                $imgline_html .= " <a href='/image.php?i=" . urlencode($image_url) . "'>[$img_num]</a> ";
            }
        endforeach;
        if($img_num>0) {
            echo  $imgline_html ;
        }
    ?></small></p>
    <?php if($error_text) { echo "<p><font color='red'>" . $error_text . "</font></p>"; } ?>
    <p><font size="4"><?php echo $readable_article;?></font></p>
 </body>
 </html>