<?php

define("PARSEDOWN_PATH", dirname(__FILE__)."/lib/parsedown/Parsedown.php");
define("INPUT_DIR",	dirname(__FILE__)."/input/");
define("OUTPUT_DIR",	dirname(__FILE__)."/output/");

define("EXCLUDE_EXTS", ['git', 'tmp']);
define("LOCAL_DOMAIN", 'example.com');





// ---

$header = file_get_contents("./templates/header.html");
$footer = file_get_contents("./templates/footer.html");

require_once(PARSEDOWN_PATH);

$pd = new Parsedown();
$pd->setSafeMode(true);

$dom = new DOMDocument();

foreach(getFileList(INPUT_DIR) as $file) {
	$pi = pathinfo($file);
	$pi["dst_dirname"] = OUTPUT_DIR . mb_substr($pi["dirname"], mb_strlen(INPUT_DIR));
	
	
	if(in_array($pi['extension'], EXCLUDE_EXTS)) { continue; } // 除外対象の拡張子だったらスキップ
	
	if(is_dir($pi['dst_dirname']) === FALSE) {
		mkdir($pi['dst_dirname']);
	}
	
	if($pi["extension"] == "md") {
		$html = '';
		$html .= sprintf("%s\n", trim($header));
		$html .= $pd->text(file_get_contents($file));
		$html .= sprintf("\n%s", trim($footer));
		
		// Aタグを走査して、自ドメインのmdファイル宛だったら拡張子をhtmlに書き換える
		$dom->loadHTML($html);
		$xml = simplexml_load_string($dom->saveXML());
		foreach ($xml->xpath('//a') as $el) { // XPathにてAタグを探す
		
			if(preg_match('/.md$/', $el['href']) === 0) { continue; } // href内が ".md" で終わってない場合はスキップ
			if(!isHrefLocal(LOCAL_DOMAIN, $el['href'])) { continue; } // リンク先がリモートっぽかったらスキップ
			
			$el['href'] = preg_replace('/\.md$/', '.html', $el['href']); // .md を .html に置換
		}
		$html = $xml->asXML();
		//var_dump($xml);
		
		
		// HTMLファイルを出力する
		file_put_contents($pi['dst_dirname'] .'/'. $pi['filename'] . '.html', $html);
	} else {
		// マークダウン以外はファイルをまるっとコピー
		copy($pi['dirname'].'/'.$pi['basename'], $pi['dst_dirname'].'/'.$pi['basename']);	
	}
}

exit(0);



function getFileList($dir) {
	$files = glob(rtrim($dir, '/') . '/*');
	$list = array();
	foreach ($files as $file) {
		if (is_file($file)) {
			$list[] = $file;
		}
		if (is_dir($file)) {
			$list = array_merge($list, getFileList($file));
		}
	}
	return $list;
}

function isHrefLocal($domain=Null, $href=Null) {
	
	if(!isset($domain, $href)) { return false; }
	
	// (プロトコル指定は任意)自らのドメインを含むリンクは内部リンクと見なす
	if(preg_match('/^(https?:)?\/\/'.$domain.'/', $href) === 1) { return true; }
	
	// (プロトコル指定は任意)それ以外のドメインを含むリンクは外部リンクと見なす
	if (preg_match('/^(https?:)?\/\//', $href) === 1) { return false; }
	
	// それ以外は内部リンクと見なす（プロトコル指定のない、相対リンク等）
	return true;
}