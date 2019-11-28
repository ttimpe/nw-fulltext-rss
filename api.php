<?php
require_once('simple_html_dom.php');
require_once('models/Article.php');

$COOKIEJAR = "cookies.txt";
error_reporting(0);
header('Content-Type: application/rss+xml');

function login_user($username, $password) {
	global $COOKIEJAR;
	$login_url = 'https://www.nw.de/profil';
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $login_url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt($ch, CURLOPT_AUTOREFERER, true );

	curl_setopt($ch, CURLOPT_POSTFIELDS, "em_cnt=&ref=&my_user=".$username."&my_pass=".$password."&my_auto_login=1");
	curl_setopt($ch, CURLOPT_COOKIESESSION, true);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $COOKIEJAR);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $COOKIEJAR);
// In real life you should use something like:
// curl_setopt($ch, CURLOPT_POSTFIELDS, 
//          http_build_query(array('postvar1' => 'value1')));

// Receive server response ...
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$server_output = curl_exec($ch);

	curl_close ($ch);
// Further processing ...
}

function check_login($dom) {
	$text = $dom->find('.text-login')[0]->plaintext;
	if ($text == 'Profil') {
		return true;
	} else {
		return false;
	}
}

function get_with_cookies($url) {
	global $COOKIEJAR;
	$ch =  curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_COOKIESESSION, true);
	curl_setopt($ch, CURLOPT_COOKIEJAR,  $COOKIEJAR);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $COOKIEJAR);

	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}

function get_article_urls_from_overview($url) {
	$html = str_get_html(get_with_cookies($url));
	$section_content = $html->find('.section-content')[0];
	$articleContainers = $section_content->find('article.article');
	$article_urls = [];
	foreach ($articleContainers as $articleContainer) {
		$h3 = $articleContainer->find('.article-headline-object')[0];
		$link = $h3->find('a')[0]->href;
		$article_urls[] = $link;
	}
	return $article_urls;
}


function get_article_from_url($url) {
	$article = new Article();
	$html = str_get_html(get_with_cookies($url));
	$scripts = $html->find('script');
	$schema = null;
	foreach ($scripts as $script) {
		$type = $script->type;
		if (strpos($type, 'json')) {
			
			$schema = json_decode($script->innertext);
		}
	}

	$article->id = intval($schema->name);
	$article->title = $schema->headline;
	$article->url = $url;
	$article->description = $schema->description;
	$article->datePublished = new DateTime($schema->datePublished);
	$article->dateModified = new DateTime($schema->dateModified);
	$article->thumbnailURL = $schema->thumbnailURL;
	$article->keywords = $schema->keywords;
	$article->section = $schema->articleSection;
	foreach($schema->author as $author) {
		$article->authors[] = $author->name;
	}
	$content = $html->find('.article-detail-entry-content')[0];
	$content->find('.article-detail-paid-content',0)->outertext = '';
	$scripts_in_content = $content->find('script');
	foreach($scripts_in_content as $s) {
		$s->outertext = '';
	}
	$article->content = trim($content->innertext);

	return $article;

}

function get_article_pages($section_base_url) {
	$article_urls = [];
	for ($i=1; $i<11; $i++) {
		$overview_page_url = $section_base_url.'?em_index_page='.$i;
		$article_urls = array_merge($article_urls, get_article_urls_from_overview($overview_page_url)); 
	}
	return $article_urls;
}

function render_rss() {
	global $section_base_url;
	$article_urls = get_article_pages($section_base_url);

	$articles = [];
	foreach ($article_urls as $article_url) {
		$article = get_article_from_url($article_url);

		echo "\t\t<item>".PHP_EOL;
		echo "\t\t\t<guid>".$article->url."</guid>".PHP_EOL;
		echo "\t\t\t<link>".$article->url."</link>".PHP_EOL;
		echo "\t\t\t<author>digitalredaktion@nw.de (<![CDATA[".implode(', ', $article->authors)."]]>)</author>".PHP_EOL;
		echo "\t\t\t<pubDate>".$article->dateModified->format(DateTime::RFC822)."</pubDate>".PHP_EOL;
		echo "\t\t\t<title><![CDATA[".$article->title."]]></title>".PHP_EOL;
		echo "\t\t\t<description><![CDATA[".$article->content."]]></description>".PHP_EOL;
		echo "\t\t</item>".PHP_EOL;
	}
}

function main(){
	global $section_base_url;
	echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>".PHP_EOL;
	echo "<rss version=\"2.0\">".PHP_EOL;
	echo "\t<channel>".PHP_EOL;
	echo "\t\t<title>NW</title>".PHP_EOL;
	echo "\t\t<link>".$section_base_url."</link>".PHP_EOL;
	echo "\t\t<description>NW</description>".PHP_EOL;
	echo "\t\t<language>de-de</language>".PHP_EOL;
	echo "\t\t<copyright>NW</copyright>".PHP_EOL;
	//echo '\t\t<pubDate>'.date()."</pubDate>".PHP_EOL;
	render_rss();
	echo "\t</channel>".PHP_EOL;
	echo "</rss>";
}

$section = '';
if (isset($_GET['section'])) {
	$section = $_GET['section'];
}
$section_base_url = 'https://nw.de/lokal/'.$section.'/uebersicht';
if (isset($_GET['username']) && isset($_GET['password'])) {
	do_login($_GET['username'], $_GET['password']);
}
main();

?>