<?php // $kitku->dump($kitku); ?>

<!DOCTYPE html>
<html>
<head>

    <meta charset="utf-8">
    
    <title><?php 
        if ($kitku->p === 'post') { echo $kitku->title; }
        else if ($kitku->p === 'page') { echo $kitku->siteName.' | '.$kitku->title; }
        else { echo $kitku->siteName; }
    ?></title>

	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" href="<?= $kitku->home['url'].'theme/normalize.css'?>">
	<link rel="stylesheet" type="text/css" href="<?= $kitku->home['url'].'theme/style.css'?>">
    <link rel="icon" type="image/png" href="<?= $kitku->home['url'].'theme/favicon.png'?>"/>
    
	<script>
	const installUrl = '<?= $kitku->home['installUrl'] ?>',
		homeUrl = '<?= $kitku->home['url'] ?>',
        imageMaxSizes = JSON.parse('<?= json_encode($kitku->imageMaxSizes) ?>'),
        currentPage = '<?= $kitku->title ?: 'home' ?>'
    </script>
    
</head>

<body>

    <div id="banner">
        <a href="<?= $kitku->home['url'] ?>">
            <div id="site-name">
                <?= $kitku->siteName ?>
            </div>
        </a>
        <div id="nav-container">
            <?php 
            foreach($kitku->get_nav_links() as $link) {
                echo('<div class=\'nav-link\'><a href="'.$kitku->home['url'].$link.'">'.$link.'</a></div>');
            } 
            ?>
        </div>
    </div>

    <div id="main">

        <div id="title">
            <?= $kitku->title ?>    
        </div>

        <hr>

        <?php
            $mainImage = $kitku->get_main_image($kitku->urlTitle);
            if ($mainImage) {
                echo('
                    <div id="main-image">
                        <img src="'.$mainImage.'">
                    </div>
                ');
            }
        ?>

        <div id="content">
            <div class="sub-image">
                <?= $kitku->get_content_data($kitku->urlTitle, true, 'max')[2] ?>
            </div>
        </div>

        <?php if (!empty($kitku->blogPage) && $kitku->blogPage == true) { ?>
        <div id="posts">
            <?php
            foreach($kitku->get_post_snippets(4) as $value) {
                $imagePath = $kitku->get_smallest_main_image($value['urlTitle'], true);
                echo('
                    <a class="post-link" href="'.$kitku->home['url'].$value['urlTitle'].'">
                        <div class="post-container">
                            <div class="post-image">
                                <img src="'. $imagePath .'">
                            </div>
                            <div class="post-title">
                                '. $value['title'] .'
                            </div>
                            <div class="post-content">
                                <div>
                                    '. strip_tags(substr($value['content'], 0, 280)) .'...
                                </div>
                            </div>
                        </div>
                    </a>
                ');
            }
            ?>
        </div>
        <?php } ?>

    </div>
</body>
</html>