<?php
    require_once('helpers.php');

    $url = "";
    
    //get the image url
    if (isset( $_GET['i'] ) ) {
        $url = $_GET[ 'i' ];
    } else {
        exit();
    }

    //we can only do jpg and png here
    if (!frog_image_type($url)) {
        echo "Unsupported file type :(";
        exit();
    }

    //image needs to start with http
    if (substr( $url, 0, 4 ) != "http") {
        echo("Image failed :(");
        exit();
    }

    $back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 2.0//EN">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
 
 
 <html>
 <head>
     <title>FrogFind Image Viewer</title>
 </head>
 <body>
    <small><a href="<?php echo h($back) ?>">&lt; Back to previous page</a></small>
    <p><small><b>Viewing image:</b> <?php echo h($url) ?></small></p>
    <img src="/image_compressed.php?i=<?php echo urlencode($url); ?>">
    <br><br>
    <small><a href="<?php echo h($back) ?>">&lt; Back to previous page</a></small>
 </body>
 </html>