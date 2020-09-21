<?php

require_once 'kitku.php';
require_once 'res\htmlpurifier-4.13.0\library\HTMLPurifier.auto.php';

class Admin extends Kitku {

	public $tags =[] ;
	public $categories = [];

	private $purifier;
	private $imagePath;

	function __construct() {
		parent::__construct();

		$this->imagePath = $this->home['server'].'images/';

		if ($this->installed !== true) {
			$this->redirect_url();
			exit();
		}
	}

	function __destruct() {
		parent::__destruct();
	}

	public function get_data($data, $target = null) {

		switch ($data) {
			case 'posts':
			case 'pages':
				$allPosts = $this->select('*', $data);
				foreach ($allPosts as $key => $value) {
					if (!empty($allPosts[$key]['date'])) {
						$allPosts[$key]['date'] = date("h:i M d, Y",$allPosts[$key]['date']);
					}
				}
				return (json_encode($allPosts, JSON_PRETTY_PRINT));
			break;
			case 'editor':
				$tagsArray = $this->select('tags', 'posts');
				$everyTag = [];
				foreach($tagsArray as $arr) {
					foreach($arr as $key => $value) {
						$values = explode(',', $value);
						foreach($values as $value) {
							if ($value != ' ' && $value != '') {
							array_push($everyTag, trim($value));

							}
						}
					}
				}
				$frequency = array_count_values($everyTag);
				arsort($frequency);
				foreach($frequency as $key => $value) {
					array_push($this->tags, $key);
				}

				$categoriesArray = $this->select('category', 'posts');
				for($i=0; $i<count($categoriesArray); $i++) {
					foreach($categoriesArray[$i] as $key => $value) {
						if (!in_array($value, $this->categories)) {
							if ($value != '' && $value != ' ') {
								array_push($this->categories, $value);
							}
						}
					}
				}
				sort($this->categories);
				return json_encode([$this->tags, $this->categories]);
			break;
			default:
				$info = $this->select('*', 'pages', 'urlTitle='.$data);
				if (!$info) {
					$info = $this->select('*', 'posts', 'urlTitle='.$data);
				}

				$mainImage = $this->get_smallest_main_image($data);
				if ($mainImage) {
					$info[0]['mainImage'] = str_replace($this->imagePath, '', $mainImage);
				}

				$contentData = $this->get_content_data($data);
				$images = $contentData[0];
				$imgSrc = $contentData[1];
				$content = $contentData[2];

				for($i = 0; $i < count($contentData[1]); $i++) {
					$content = str_replace($imgSrc[$i], '<img src="'.$images[trim(substr($imgSrc[$i], 9),'"')]['source'].'"', $content);
				}

				$info[0]['content'] = $content;

				return ($info) ? json_encode($info[0]) : false;
			break;
		}
		return false;
	}

	public function new_post(array $postData, array $imageData) {
		
		$title = $this->handle_title($postData['editor-title']);

		$urlTitle = $this->strip_special_chars($title);

		if ($this->select(['title'], 'posts', ['title='.$title, 'urlTitle='.$urlTitle], 'OR')) {
			return 'titleTaken';
		}

		$tags = $this->handle_tags($postData['editor-tags']);

		$category = $this->handle_category($postData['editor-category']);

		try {
			$this->handle_images($imageData, $postData, $urlTitle);
		} catch (Exception $e) {
			return $e->getMessage();
		}

		$content = $this->handle_content($postData['editor-content']);

		$insert = [
			'title' => $title,
			'urlTitle' => $urlTitle,
			'author' => $_SESSION['user'],
			'category' => $category,
			'date' => time(),
			'tags' => $tags,
			'views' => 0,
			'content' => $content
		];

		if ($this->insert('posts', $insert)) {
			return 'success,'.$urlTitle;
		} else {
			return 'serverError';
		}
	}

	public function edit_post(array $postData, array $imageData, string $original) {
		$title = $this->handle_title($postData['editor-title']);

		$oldData = $this->select('*', 'posts', 'urlTitle='.$original)[0];

		$urlTitle = $this->strip_special_chars($title);
		$tempUrlTitle = time().'_'.$this->random_string(5);

		$tags = $this->handle_tags($postData['editor-tags']);

		$category = $this->handle_category($postData['editor-category']);

		try {
			$this->handle_images($imageData, $postData, $tempUrlTitle);
		} catch (Exception $e) {
			return $e->getMessage();
		}

		$content = $this->handle_content($postData['editor-content']);

		$insert = [
			'title' => $title,
			'urlTitle' => $tempUrlTitle,
			'author' => $oldData['author'],
			'category' => $category,
			'date' => $oldData['date'],
			'tags' => $tags,
			'views' => $oldData['views'],
			'content' => $content
		];

		if ($this->insert('posts', $insert)) {

			if (!$imageData['name'] && $postData['remove-main-image'] !== 'true') {
				$originalMainImages = glob($this->imagePath.$original.'/main_*');
				foreach($originalMainImages as $filename) {
					rename($filename, $this->imagePath.$tempUrlTitle.(str_replace($this->imagePath.$original, '', $filename)));
				}	
			}

			$this->delete('posts', ['urlTitle='.$original]);
			$this->delete_files($this->imagePath.$original.'/');

			$this->update('posts', ['urlTitle' => $urlTitle], ['urlTitle='.$tempUrlTitle]);
			rename($this->imagePath.$tempUrlTitle, $this->imagePath.$urlTitle);

			return 'success,'.$urlTitle;
		} else {
			return 'serverError';
		}
	}

	public function delete_post($post) {
		$this->delete_files($this->imagePath.$post);
		return $this->delete('posts', ['urlTitle='.$post]);
	}

	private function handle_title($title) {
		$this->set_purifier();
		$title = trim($title);
		return $this->purifier->purify($title);
	}

	private function handle_tags($tags) {
		$tags = strtolower($tags);
		$tags = trim($tags);
		$tags = preg_replace('/,\s*/', ',', $tags);
		return str_replace(' ', '-', $tags);
	}

	private function handle_category($category) {
		$this->set_purifier();

		$category = trim($category);
		return $this->purifier->purify($category);
	}

	private function handle_images($imageData, $postData, $urlTitle) {
		$imagePath = $this->home['server'].'images/'.$urlTitle.'/';

		if (!file_exists($imagePath)) {
			mkdir($imagePath);
		}
		$images = json_decode($postData['images']);
		if (!empty($images)) {
			foreach($images as $key => $value) {
				$image = new KitkuImage($value, $imagePath, $key);
				if ($image->error) {
					throw new Exception('imageError'.$image->error.' on image: '.intval($key)+1);
					return false;
				} else {
					$image->save($this->imageMaxSizes);
				}
			}
		}
		
		if (!empty($imageData['tmp_name'])) {
			$imageMain = new KitkuImage($imageData['tmp_name'], $imagePath, 'main');
			if ($imageMain->error) {
				throw new Exception('imageError'.$image->error.' on Main Image.');
				return false;
			} else {
				$imageMain->save($this->imageMaxSizes);
			}
		}
		return true;
	}

	private function handle_content($content) {
		$this->set_purifier();
		return $this->purifier->purify($content);
	}

	private function get_smallest_main_image($urlTitle) {
		foreach($this->imageMaxSizes as $key => $value) {
			$smallest = $key;
			break;
		}
		$glob = glob($this->imagePath.$urlTitle.'/main_'.$smallest.'.*');
		return !empty($glob[0]) ? $glob[0] : false;
	}

	public function delete_files($target) {
		if(is_dir($target)){
			$files = glob($target.'*', GLOB_MARK );
	
			foreach( $files as $file ){
				$this->delete_files($file);      
			}
	
			if(is_dir($target)){
				rmdir($target);
			}
		} elseif(is_file($target)) {
			unlink($target);  
		}
	}

	private function set_purifier() {
		if (empty($this->purifier)) {
			$this->purifier = new HTMLPurifier();
		}
	}
}

$kitku = new Admin();

if ($kitku->check_login() !== true) {
	$kitku->demand_login('admin');
	exit();
}

/* -- AJAX FUNCTIONS -- */
if (!empty($_POST)) {
	switch($_POST['func']) {
		case 'logout':
			echo(($kitku->logout()) ? 'success' : 'failed');
		break;
		case 'get_data':
			echo $kitku->get_data($_POST['page']);
		break;
		case 'new-post':
			echo($kitku->new_post($_POST, $_FILES['editor-image']));
		break;
		case 'get-post':
			echo($kitku->get_data($_POST['post']));
		break;
		case 'edit-post':
			echo($kitku->edit_post($_POST, $_FILES['editor-image'], $_POST['original']));
		break;
		case 'delete-post':
			echo($kitku->delete_post($_POST['post']));
		break;
		case 'new-page':
			echo($kitku->new_page($_POST, $_FILES['editor-image']));
		break;
		case 'get-page':
			echo($kitku->edit_page($_POST, $_FILES['editor-image']));
		break;
		case 'edit-page':
			echo($kitku->edit_page($_POST, $_FILES['editor-image']));
		break;
		case 'delete-page':
			echo($kitku->delete_page($_POST['post']));
		break;
	}
	exit();
}

include $kitku->home['installServer'].'res/header.php';

?>

<script>
	const buildTableIgnores = JSON.parse('<?= json_encode($kitku->buildTableIgnores); ?>'),
		buildTableToggles = JSON.parse('<?= json_encode($kitku->buildTableToggles); ?>');
</script>

<body>

	<div id="admin">

		<div id="navbar">

			<img id="kitku-logo" class="navbar-logo" width="64px" height="64px" src="<?= $kitku->home['installUrl'].'res/images/logo.png' ?>" />

			<div id="navbar-item-container">

				<div data-page="home" class="navbar-item active">
					<svg width="24" height="24" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d=" M 500 75C 514 75 528 82 540 94C 540 94 950 504 950 504C 960 514 960 519 950 529C 950 529 915 564 915 564C 905 574 895 574 885 564C 885 564 515 194 515 194C 505 184 495 184 485 194C 485 194 115 564 115 564C 105 574 95 574 85 564C 85 564 50 529 50 529C 40 519 40 514 50 504C 50 504 135 419 135 419C 145 409 150 399 150 389C 150 389 150 267 150 267C 150 267 150 154 150 154C 150 139 160 129 175 129C 175 129 300 129 300 129C 315 129 326 139 325 154C 325 154 325 229 325 229C 325 229 460 94 460 94C 472 82 486 75 500 75C 500 75 500 75 500 75M 500 236C 505 236 510 239 515 244C 515 244 835 564 835 564C 845 574 850 579 850 594C 850 594 850 879 850 879C 850 914 835 929 800 929C 800 929 600 929 600 929C 590 929 575 914 575 904C 575 904 575 754 575 754C 575 739 565 729 550 729C 550 729 450 729 450 729C 435 729 425 739 425 754C 425 754 425 904 425 904C 425 914 410 929 400 929C 400 929 200 929 200 929C 165 929 150 914 150 879C 150 879 150 594 150 594C 150 579 155 574 165 564C 165 564 485 244 485 244C 490 239 495 236 500 236C 500 236 500 236 500 236"/></svg>
					<div>home</div>
				</div>
				<div data-page="posts" data-children="new-post" class="navbar-item">
					<svg width="24" height="24" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d=" M 725 88C 746 88 762 104 763 125C 763 125 763 188 763 188C 763 242 725 287 675 287C 675 287 641 287 641 287C 641 287 659 466 659 466C 690 473 717 489 734 513C 756 544 763 583 763 625C 762 646 746 662 725 663C 725 663 600 663 600 663C 600 663 563 663 563 663C 563 663 437 663 437 663C 437 663 400 663 400 663C 400 663 275 663 275 663C 254 662 238 646 238 625C 238 583 244 544 266 513C 283 489 310 473 341 466C 341 466 359 287 359 287C 359 287 325 287 325 287C 300 287 279 276 264 261C 249 246 238 225 238 200C 238 158 238 167 238 125C 238 104 254 88 275 88C 275 88 725 88 725 88M 563 710C 563 710 563 850 563 850C 563 856 561 862 559 867C 559 867 534 917 534 917C 527 929 514 937 500 937C 486 937 473 929 466 917C 466 917 441 867 441 867C 439 862 438 856 437 850C 437 850 437 710 437 710C 437 710 563 710 563 710"/></svg>
					<div>posts</div>
				</div>
				<div data-page="pages" data-children="new-page" class="navbar-item">
					<svg width="24" height="24" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d=" M 200 166C 200 166 200 166 200 166C 204 129 238 98 275 99C 375 99 475 100 575 100C 575 168 574 236 576 304C 582 330 612 323 632 324C 632 324 798 324 798 324C 799 493 800 663 800 833C 796 876 753 905 711 900C 563 900 415 901 267 900C 224 896 195 853 200 811C 200 596 200 381 200 166M 625 100C 625 100 625 100 625 100C 631 101 637 103 641 107C 692 157 743 206 794 256C 802 267 800 275 800 275C 742 276 683 275 625 275C 625 275 625 100 625 100"/></svg>
					<div>pages</div>
				</div>
				<div data-page="settings" class="navbar-item">
					<svg width="24" height="24" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d=" M 500 328C 500 328 500 328 500 328C 405 328 328 405 328 500C 328 595 405 672 500 672C 595 672 672 595 672 500C 672 405 595 328 500 328M 463 101C 463 101 463 101 463 101C 463 101 534 101 534 101C 558 101 578 118 582 142C 582 142 592 198 592 198C 611 203 630 211 648 221C 648 221 694 188 694 188C 713 174 739 177 758 193C 758 193 807 243 807 243C 825 260 827 287 813 306C 813 306 780 352 780 352C 789 370 796 388 802 407C 802 407 858 416 858 416C 882 420 899 441 899 465C 899 465 899 535 899 535C 899 559 882 580 858 584C 858 584 802 593 802 593C 796 612 789 630 779 648C 779 648 812 694 812 694C 826 714 824 740 807 757C 807 757 757 807 757 807C 741 822 714 826 694 812C 694 812 648 779 648 779C 630 789 612 796 593 802C 593 802 584 858 584 858C 580 882 559 899 535 899C 535 899 465 899 465 899C 441 899 420 882 416 858C 416 858 407 802 407 802C 389 796 371 789 354 780C 354 780 307 814 307 814C 288 827 263 825 244 808C 244 808 194 759 194 759C 177 742 175 715 189 695C 189 695 222 649 222 649C 212 632 205 614 199 595C 199 595 142 586 142 586C 118 582 101 561 101 537C 101 537 101 467 101 467C 101 442 118 422 142 418C 142 418 197 409 197 409C 203 390 210 372 220 354C 220 354 186 307 186 307C 172 288 175 261 192 244C 192 244 241 194 241 194C 257 179 285 175 305 189C 305 189 351 222 351 222C 368 212 386 205 405 199C 405 199 415 142 415 142C 419 118 439 101 463 101"/></svg>
					<div>settings</div>
				</div>

			</div>

			<a class="dummy-link" href="https://github.com/Y0ss-Please/Kitku">
				<div data-page="github" class="navbar-item">
					<svg width="24" height="24" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d=" M 500 0C 500 0 500 0 500 0C 776 0 1000 224 1000 500C 1000 720 857 908 659 974C 634 979 625 963 625 950C 625 933 626 879 626 813C 626 766 610 736 592 720C 703 708 820 665 820 473C 820 418 801 374 769 339C 774 327 791 275 764 207C 764 207 722 193 626 258C 586 247 544 241 501 241C 459 241 416 247 376 258C 281 194 239 207 239 207C 211 275 229 327 234 339C 202 374 183 419 183 473C 183 665 299 708 410 720C 396 733 383 755 378 787C 349 800 278 821 233 746C 223 731 195 694 156 695C 114 695 139 718 156 728C 178 740 202 784 208 798C 218 826 250 880 376 857C 376 899 376 938 376 950C 376 963 367 978 342 974C 143 908 0 721 0 500C 0 224 224 0 500 0"/></svg>
					<div>github</div>
				</div>
			</a>

		</div>

		<div id="main-header">
			<span>Icons from <a href="https://friconix.com/">Friconix</a></span>
			<span>Kitku alpha-<?= $kitku->version ?></span>
			<button id="logout-button" class="button">Logout</button>
		</div>

		<div id="main">

			<div id="main-modal" class="hidden">
				<div>
					<div>
						<div id="main-modal-busy" class="surge"></div>
						<div class="main-modal-content">
							<h2 id="main-modal-header">Whoa there, partner!</h2>
							<div id="main-modal-message">No need to get all uppity about it.</div>
							<div id="main-modal-button" class="button" style="margin: 0 1em 1em">okay</div>
						</div>
					</div>
				</div>
			</div>

			<div data-page="home" class="main-content active">
				<h1>The home page</h1>
				<hr />
			</div>

			<div data-page="posts" class="main-content">
				<div class="page-title-container">
					<h1 class="page-title"><?= $kitku->siteName ?>'s Posts</h1>
					<div data-page="editor" class="button new-button">New Post</div>
				</div>
				<hr>
				<div class="table-container"">
					<table id="posts-table">
						<thead>
							<tr>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div>

			<div data-page="pages" class="main-content">

				<div class="page-title-container">
					<h1 class="page-title"><?= $kitku->siteName ?>'s Pages</h1>
					<div data-page="new-page" class="button new-button">New Page</div>
				</div>
				<hr>
				<div class="table-container"">
					<table id="pages-table">
						<thead>
							<tr>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div>

			<div data-page="settings"class="main-content">
				<h2>A setting page, eventually...</h2>
			</div>

			<div data-page="github" class="main-content">
				<h2>Redirecting to github...</h2>
			</div>

			<!-- Sub Pages -->
			<div data-page="editor" class="main-content" data-type="">
				<div class="page-title-container">
					<h1 id="editor-page-title" class="page-title">New Post</h1>
					<div id="editor-button" class="button">Save</div>
				</div>
					<hr />
					<form name="editor" class="form-editor form-grid" autocomplete="off" onsubmit="return false">
						<label for="editor-title">Title: </label>
						<input id="editor-title" type="text" name="editor-title" required></input>
						<label for="editor-tags">Tags: </label>
						<div>
							<input id="editor-tags" type="text" name="editor-tags" placeholder="Seperated by commas. Letters and numbers only." onfocus="this.value =  this.value"></input>
							<div id="editor-tags-container">
							</div>
						</div>
						<label for="editor-category">Category: </label>
						<div id="editor-category-container">
							<input id="editor-category" type="text" name="editor-category"></input>
							<div id="category-dropdown" class="hidden"></div>
						</div>
						<label for="editor-image">Main Image: </label>
						<div id="main-image-preview-container">
							<div class="relative margin-1">
								<img id="main-image-preview" src="" />
								<div id="main-image-preview-overlay" class="hidden">Hello World</div>
							</div>
							<div>
								<div id="main-image-change" class="button">Change</div>
								<div id="main-image-remove" class="button">Remove</div>
							</div>
						</div>
						<input id="main-image-input" class="hidden" type="file" name="editor-image" accept="image/png, image/jpeg, image/gif"></input>
						<input id="editor-submit" class="hidden" type="submit"></input>
					</form>
					<br>
					<div class="editor-container">
						<div class="editor-content editor" id="editor-content"></div>
					</div>
				</div>
			</div>

			<div data-page="new-page" class="main-content">
				<h2>New Page Page!</h2>
				<hr />
				<div class="editor-container">
					<div class="editor" id="page-editor"></div>
				</div>
			</div>

		</div>
	</div>

	<svg class="icon-edit hidden" width="24" height="24" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d=" M 229 615C 229 615 382 766 382 766C 382 766 356 792 356 792C 351 798 344 800 338 802C 338 802 210 828 210 828C 186 832 163 810 169 785C 169 785 195 659 195 659C 195 652 199 646 203 641C 203 641 229 615 229 615M 713 137C 713 137 865 288 865 288C 865 288 432 716 432 716C 432 716 279 565 279 565C 279 565 713 137 713 137M 839 25C 848 25 858 29 865 36C 865 36 967 137 967 137C 980 150 980 173 967 187C 967 187 915 237 915 237C 915 237 763 86 763 86C 763 86 815 36 815 36C 821 29 830 25 839 25C 839 25 839 25 839 25M 150 13C 150 13 650 13 650 13C 664 12 676 19 683 31C 690 43 690 57 683 69C 676 81 664 88 650 88C 650 88 150 88 150 88C 138 88 121 95 108 108C 95 121 88 138 88 150C 88 150 88 850 88 850C 88 862 95 879 108 892C 121 905 138 912 150 912C 150 912 850 912 850 912C 862 912 879 905 892 892C 905 879 912 862 912 850C 912 850 912 350 912 350C 912 336 919 324 931 317C 943 310 957 310 969 317C 981 324 988 336 987 350C 987 350 987 850 987 850C 987 887 970 920 945 945C 921 970 888 987 850 987C 850 987 150 987 150 987C 113 987 79 970 55 945C 30 921 13 887 13 850C 13 850 13 150 13 150C 13 113 30 79 55 55C 79 30 113 13 150 13C 150 13 150 13 150 13"/></svg>

	<svg class="icon-delete hidden" width="24" height="24" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d=" M 357 378C 344 378 332 390 333 403C 333 403 329 848 329 848C 329 857 333 866 341 870C 349 875 359 875 366 870C 374 866 379 858 379 849C 379 849 383 404 383 404C 383 397 380 391 375 386C 371 381 364 378 357 378C 357 378 357 378 357 378M 650 375C 636 375 625 386 625 400C 625 400 625 850 625 850C 625 859 630 867 637 872C 645 876 655 876 663 872C 670 867 675 859 675 850C 675 850 675 400 675 400C 675 393 672 387 668 382C 663 377 656 375 650 375C 650 375 650 375 650 375M 500 375C 486 375 475 386 475 400C 475 400 475 850 475 850C 475 859 480 867 487 872C 495 876 505 876 513 872C 520 867 525 859 525 850C 525 850 525 400 525 400C 525 393 522 387 518 382C 513 377 506 375 500 375C 500 375 500 375 500 375M 198 299C 198 299 800 299 800 299C 800 299 800 850 800 850C 800 913 759 950 700 950C 700 950 300 950 300 950C 238 950 200 911 201 855C 201 855 198 299 198 299M 438 138C 438 138 438 187 438 187C 438 187 563 187 563 187C 563 187 563 138 563 138C 563 138 438 138 438 138M 425 63C 425 63 575 63 575 63C 609 63 638 91 638 125C 638 125 638 187 638 187C 638 187 849 187 849 187C 870 187 887 204 887 225C 887 245 870 262 849 262C 849 262 151 263 151 263C 130 263 113 246 113 225C 113 205 130 188 151 188C 151 188 363 188 363 188C 363 188 363 125 363 125C 363 125 362 125 362 125C 362 91 391 63 425 63C 425 63 425 63 425 63"/></svg>

	<script type="module" src="<?= $kitku->home['installUrl'].'res/js/admin.js' ?>"></script>
	<script src="<?= $kitku->home['installUrl'].'res/js/quill.js' ?>"></script>

</body>
</html>