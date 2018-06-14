<?php



$options = [
	// -- 設定必須項目
	'PARSEDOWN_PATH'	=> dirname(__FILE__)."/lib/parsedown/Parsedown.php",
	'INPUT_DIR'			=> dirname(__FILE__)."/input/",
	'OUTPUT_DIR'		=> dirname(__FILE__)."/output/",
	'OVERWRITE'			=> true,
	'FORCE_RECREATE'	=> true,
	
	// -- 設定任意項目
	'HEADER_HTML'		=> dirname(__FILE__)."/templates/header.html",
	'FOOTER_HTML'		=> dirname(__FILE__)."/templates/footer.html",
//	'EXCLUDE_EXTS'		=> ['tmp', 'git', 'gitignore'],
//	'EXCLUDE_DIRS'		=> [],
	'LOCAL_DOMAIN'		=> 'example.com',
	
	// --- チェックボックススタイル
	'CHECKBOX_STYLE'	=> 'none', // none or form or unicode or custom
	'CHECKBOX_CUSTOM_TRUE'	=> '■',
	'CHECKBOX_CUSTOM_FALSE'	=> '□',
];












// ---

// -- 固定設定
define('CHECKBOX_FORM_TRUE',  '<input type="checkbox" disabled="disabled" checked="checked">');
define('CHECKBOX_FORM_FALSE', '<input type="checkbox" disabled="disabled">');
define('CHECKBOX_UNICODE_TRUE',  '&#x2611;');
define('CHECKBOX_UNICODE_FALSE', '&#x2610;');


// -- Parsedownの読み込み
if(!is_file($options['PARSEDOWN_PATH'])) { print "ERROR: Parsedown not found.\n"; exit(1); }
require_once($options['PARSEDOWN_PATH']);


// -- 入出力ディレクトリの存在確認
if(!is_dir($options['INPUT_DIR'])) { print "ERROR: INPUT_DIR is not directory.\n";  exit(1); }
if(!is_dir($options['OUTPUT_DIR'])){ print "ERROR: OUTPUT_DIR is not directory.\n"; exit(1); }


// -- オプション正規化

// --- 配列系
if(!isset($options['EXCLUDE_EXTS'])) { $options['EXCLUDE_EXTS'] = []; }
if(!is_array($options['EXCLUDE_EXTS']))  { $options['EXCLUDE_EXTS']  = [$options['EXCLUDE_EXTS']];  }

if(!isset($options['EXCLUDE_DIRS'])) { $options['EXCLUDE_DIRS'] = []; }
if(!is_array($options['EXCLUDE_DIRS']))  { $options['EXCLUDE_DIRS']  = [$options['EXCLUDE_DIRS']];  }




// --- 文字列系

if(!isset($options['LOCAL_DOMAIN'])) { $options['LOCAL_DOMAIN'] = null; }


// ---- チェックボックス用バリデーション
// ----- スタイルが指定されていない、もしくは解釈できない場合は、noneにフォールバック
if(!isset($options['CHECKBOX_STYLE']) OR !in_array($options['CHECKBOX_STYLE'], ['none', 'form', 'unicode', 'custom']))
{ $options['CHECKBOX_STYLE'] = 'none'; }

// ----- スタイルがcustom指定にも関わらず、カスタムスタイルが指定されていない場合は、noneにフォールバック
if($options['CHECKBOX_STYLE'] == 'custom' AND !isset($options['CHECKBOX_CUSTOM_TRUE'], $options['CHECKBOX_CUSTOM_FALSE']))
{ $options['CHECKBOX_STYLE'] = 'none'; }



// -- ヘッダとフッタの読み込み（あれば）
$header = (is_file($options['HEADER_HTML'])) ? file_get_contents($options['HEADER_HTML']) : '';
$footer = (is_file($options['FOOTER_HTML'])) ? file_get_contents($options['FOOTER_HTML']) : '';







// -- メイン処理

$pd = new Parsedown();
$pd->setSafeMode(true);

$dom = new DOMDocument();


// 強制再作成が有効な場合は、出力先フォルダ配下を全て消す
if($options['FORCE_RECREATE'] === true) {
	foreach(getFileList($options['OUTPUT_DIR']) as $file) {
		printf("Deleting %s...\n", $file);
		unlink($file);
	}
}



foreach(getFileList($options['INPUT_DIR']) as $file) {
	
	$pi = pathinfo($file);
	$pi["dst_dirname"] = $options['OUTPUT_DIR'] . mb_substr($pi["dirname"], mb_strlen($options['INPUT_DIR']));
	
	if(in_array($pi['extension'],	$options['EXCLUDE_EXTS'])) { continue; } // 除外対象の拡張子だったらスキップ
	if(in_array($pi['dirname'],	$options['EXCLUDE_DIRS'])) { continue; } // 除外対象のディレクトリだったらスキップ
	
	// 出力先にディレクトリが無ければ作成を試みる
	if(is_dir($pi['dst_dirname']) === FALSE) { mkdir($pi['dst_dirname']); }
	
	// Markdownファイルの変換処理
	if($pi["extension"] == "md") {
		printf("Converting %s...\n", $file);
		
		// ヘッダ・フッタと、MarkdownをParsedownで変換したものを結合する
		$html = '';
		$html .= sprintf("%s\n", trim($header));
		$html .= $pd->text(file_get_contents($file));
		$html .= sprintf("\n%s", trim($footer));
		
		
		// チェックボックス置換が有効なら、指定されたスタイルで置き換え
		switch($options['CHECKBOX_STYLE']) {
			case 'form':
				$html = str_replace('[x]', CHECKBOX_FORM_TRUE, $html);
				$html = str_replace('[ ]', CHECKBOX_FORM_FALSE, $html);
				break;
			
			case 'unicode':
				$html = str_replace('[x]', CHECKBOX_UNICODE_TRUE, $html);
				$html = str_replace('[ ]', CHECKBOX_UNICODE_FALSE, $html);
				break;
				
			case 'custom':
				$html = str_replace('[x]', $options['CHECKBOX_CUSTOM_TRUE'], $html);
				$html = str_replace('[ ]', $options['CHECKBOX_CUSTOM_FALSE'], $html);
				break;
		}
		
		
		// Aタグを走査して、自ドメインのmdファイル宛だったら拡張子をhtmlに書き換える
		$dom->loadHTML($html);
		$xml = simplexml_load_string($dom->saveXML());
		foreach ($xml->xpath('//a') as $el) { // XPathにてAタグを探す
		
			if(preg_match('/.md$/', $el['href']) === 0) { continue; } // href内が ".md" で終わってない場合はスキップ
			
			if(!is_null($options['LOCAL_DOMAIN']) and !isHrefLocal($options['LOCAL_DOMAIN'], $el['href'])) { continue 1; } // リンク先がリモートっぽかったらスキップ
			
			$el['href'] = preg_replace('/\.md$/', '.html', $el['href']); // href中の .md を .html に置換
		}
		
		// IMGタグを走査して、altにオプションが含まれていたら解釈する
		foreach ($xml->xpath('//img') as $el) { // XPathにてimgタグを探す
		
			if(!isset($el['alt'])) { continue; } // alt属性がなければスキップ
		
			if(preg_match('/@([0-9]+)(x([0-9]+))?$/', $el['alt'], $matches) === 0) { continue; } // @高さx幅 もしくは @高さ で終わってなければスキップ
			
			switch(count($matches)) {
				case 2: // 2つマッチ＝高さのみ
					$el['height'] = (int)$matches[1];
					break;
				case 4: // 4つマッチ＝幅x高さ
					$el['width'] = (int)$matches[1];
					$el['height'] = (int)$matches[3];
					break;
			}
		}
		
		
		$html = $xml->asXML();
		
		// HTMLファイルを出力する
		file_put_contents($pi['dst_dirname'] .'/'. $pi['filename'] . '.html', $html);
		
	} else {
		// マークダウン以外はファイルをまるっとコピー
		printf("Copying %s...\n", $file);
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