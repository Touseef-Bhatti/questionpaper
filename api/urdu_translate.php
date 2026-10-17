<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json; charset=utf-8');
function urduFail(int $status, string $message): never { http_response_code($status); echo json_encode(['ok'=>false,'error'=>$message], JSON_UNESCAPED_UNICODE); exit; }
function normalizeGeminiResult(string $text, array $expectedKeys, array $fields): array {
    $expectedSet=array_fill_keys(array_map('strval',$expectedKeys),true);
    $text=trim($text);
    $text=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$text) ?? $text;
    $data=json_decode($text,true);
    if(is_string($data)){$nested=json_decode($data,true);if(is_array($nested))$data=$nested;}
    if(!is_array($data))return [];
    foreach(['translations','results','items'] as $container)if(isset($data[$container])&&is_array($data[$container])){$data=$data[$container];break;}
    if(isset($data['id'],$data['translation'])&&is_array($data['translation'])&&isset($expectedSet[(string)$data['id']]))return [(string)$data['id']=>$data['translation']];
    if(count($expectedKeys)===1){
        $onlyKey=$expectedKeys[0];
        foreach(['translation','translated','result'] as $wrapper)if(isset($data[$wrapper])&&is_array($data[$wrapper]))return [$onlyKey=>$data[$wrapper]];
        if(!array_is_list($data)){$direct=true;foreach($fields as $field)if(!isset($data[$field])||!is_string($data[$field])){$direct=false;break;}if($direct)return [$onlyKey=>$data];}
    }
    $out=[];$sequential=array_is_list($data);$position=0;
    foreach($data as $key=>$item){
        if(!is_array($item))continue;
        $id=(string)($item['id']??$item['key']??'');
        $translation=$item['translation']??$item['translated']??null;
        if($id===''&&!$sequential&&isset($expectedSet[(string)$key]))$id=(string)$key;
        if(!is_array($translation)){
            $hasFields=false;foreach($fields as $field)if(array_key_exists($field,$item)){$hasFields=true;break;}
            if($hasFields)$translation=$item;
            elseif(!$sequential&&isset($expectedSet[(string)$key])&&is_array($item)){$id=(string)$key;$translation=$item;}
        }
        if($id===''&&$sequential&&isset($expectedKeys[$position]))$id=$expectedKeys[$position];
        if($translation!==null&&isset($expectedSet[$id]))$out[$id]=$translation;
        $position++;
    }
    return $out;
}
function geminiJsonRequest(string $key,string $model,string $prompt,array $fields): array {
    $properties=[];foreach($fields as $field)$properties[$field]=['type'=>'STRING'];
    $schema=['type'=>'ARRAY','items'=>['type'=>'OBJECT','properties'=>['id'=>['type'=>'STRING'],'translation'=>['type'=>'OBJECT','properties'=>$properties,'required'=>$fields]],'required'=>['id','translation']]];
    $url='https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent?key='.rawurlencode($key);
    $payload=json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['responseMimeType'=>'application/json','responseSchema'=>$schema,'temperature'=>0.1]]);
    $body=false;$curlError='';$code=0;$response=null;
    for($attempt=0;$attempt<2;$attempt++){
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>90,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload]);
        $body=curl_exec($ch);$curlError=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $response=is_string($body)?json_decode($body,true):null;
        if($curlError==='' && $code<500)break;
        if($attempt===0)usleep(400000);
    }
    $text=$response['candidates'][0]['content']['parts'][0]['text']??'';
    if($curlError!==''||$code<200||$code>=300||!is_string($text)){
        $remoteMessage=(string)($response['error']['message']??'no candidate text');error_log('Urdu Gemini request failed: HTTP '.$code.'; cURL: '.$curlError.'; API: '.$remoteMessage);
        if(stripos($curlError,'timed out')!==false)return ['ok'=>false,'error'=>'Gemini timed out while translating. Try again in a moment.'];
        if($code===429)return ['ok'=>false,'error'=>'Gemini rate limit reached. Wait briefly, then retry.'];
        if($code>=500)return ['ok'=>false,'error'=>'Gemini is temporarily unavailable. Please retry shortly.'];
        if($code>=400)return ['ok'=>false,'error'=>'Gemini rejected the request (HTTP '.$code.'). Check the admin diagnostic page.'];
        return ['ok'=>false,'error'=>'Gemini returned no translation. Check the admin diagnostic page.'];
    }
    $finish=(string)($response['candidates'][0]['finishReason']??'unknown');
    if($finish==='MAX_TOKENS')return ['ok'=>false,'error'=>'Gemini response was cut off. Retry one item at a time.','finish'=>$finish];
    return ['ok'=>true,'text'=>$text,'finish'=>$finish];
}
set_exception_handler(static function (Throwable $error): void {
    error_log('Urdu translation endpoint failed: ' . $error->getMessage());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['ok'=>false,'error'=>'Urdu translation could not be saved. Please retry.'], JSON_UNESCAPED_UNICODE);
});
if (empty($_SESSION['urdu_translation_csrf']) || !hash_equals((string)$_SESSION['urdu_translation_csrf'], (string)($_SERVER['HTTP_X_URDU_CSRF'] ?? ''))) urduFail(403,'Please refresh the page and try again.');
$input=json_decode(file_get_contents('php://input'),true); $items=$input['items']??null;
if(!is_array($items)||!$items||count($items)>60) urduFail(422,'Choose between 1 and 60 questions.');
$forceRegeneration=filter_var($input['force']??false,FILTER_VALIDATE_BOOLEAN);
if($forceRegeneration && (empty($_SESSION['role']) || !in_array($_SESSION['role'],['admin','superadmin'],true))) urduFail(403,'Only an administrator can regenerate a saved translation.');
$maps=[
 'mcq:mcqs'=>['table'=>'mcqs','pk'=>'mcq_id','tr'=>'urduMCQs','trpk'=>'mcq_id','kind'=>'mcq','src'=>'mcqs'],
 'mcq:mcqs_from_book'=>['table'=>'mcqs_from_book','pk'=>'mcq_id','tr'=>'urduMCQs','trpk'=>'mcq_from_book_id','kind'=>'mcq','src'=>'mcqs_from_book'],
 'question:questions'=>['table'=>'questions','pk'=>'id','tr'=>'UrduQuestion','trpk'=>'question_id','kind'=>'question','src'=>'questions'],
 'question:questions_from_book'=>['table'=>'questions_from_book','pk'=>'id','tr'=>'UrduQuestion','trpk'=>'question_from_book_id','kind'=>'question','src'=>'questions_from_book'],
];
$key=trim((string)EnvLoader::get('GEMINIAPIKEYFORBOOKQUESTIONS',''));
if($key==='') urduFail(503,'Urdu translation is not configured.');
$model=trim((string)EnvLoader::get('GEMINIMODELFORBOOKQUESTIONS',EnvLoader::get('GEMINIMODEL','gemini-2.5-flash')));
$fieldsByKind=['mcq'=>['question','option_a','option_b','option_c','option_d'],'question'=>['question_text']];
$translations=[]; $missing=[]; $failed=[];
foreach($items as $item){
 $kind=(string)($item['kind']??'');$source=(string)($item['source']??'');$id=filter_var($item['id']??null,FILTER_VALIDATE_INT);$map=$maps[$kind.':'.$source]??null;
 if(!$map||!$id||$id<1) continue;
 $fields=$fieldsByKind[$kind];$cols=implode(',',array_map(static fn($f)=>"`$f`",$fields));
 $s=$conn->prepare("SELECT $cols FROM `{$map['table']}` WHERE `{$map['pk']}`=?");$s->bind_param('i',$id);$s->execute();$original=$s->get_result()->fetch_assoc();$s->close();if(!$original)continue;
 $s=$conn->prepare("SELECT $cols FROM `{$map['tr']}` WHERE source_table=? AND source_id=?");$s->bind_param('si',$map['src'],$id);$s->execute();$cached=$s->get_result()->fetch_assoc();$s->close();$resultKey="$kind:$source:$id";
 if($cached&&!$forceRegeneration){$translations[$resultKey]=$cached;continue;}
 $missing[$resultKey]=['map'=>$map,'id'=>(int)$id,'fields'=>$fields,'content'=>$original];
}
if($missing){
 foreach(array_chunk($missing,2,true) as $chunk){
  $fields=$chunk[array_key_first($chunk)]['fields'];
  // Keep kinds separate so Gemini can enforce the exact response shape for each.
  if(count(array_unique(array_map(static fn($entry)=>$entry['map']['kind'],$chunk)))>1){$byKind=[];foreach($chunk as $id=>$entry)$byKind[$entry['map']['kind']][$id]=$entry;$chunks=array_values($byKind);}else{$chunks=[$chunk];}
  foreach($chunks as $typedChunk){
   $fields=$typedChunk[array_key_first($typedChunk)]['fields'];$keys=array_keys($typedChunk);
   $inputRows=[];foreach($typedChunk as $resultKey=>$entry)$inputRows[]=['id'=>$resultKey,'content'=>$entry['content']];
   $prompt="Translate every educational item into clear Urdu script used in Pakistan. Keep meanings and scientific terms accurate; translate all student-facing text into Urdu script, while preserving formulas, standard symbols, and option labels. Return one result for every input ID. Copy each ID exactly. The response schema is mandatory.\n".json_encode($inputRows,JSON_UNESCAPED_UNICODE);
   $generated=geminiJsonRequest($key,$model,$prompt,$fields);
   $translatedById=$generated['ok']?normalizeGeminiResult($generated['text'],$keys,$fields):[];
   foreach($typedChunk as $resultKey=>$entry){
    $translation=$translatedById[$resultKey]??null;
    $complete=is_array($translation);
    if($complete)foreach($entry['fields'] as $requiredField)if(!isset($translation[$requiredField])||!is_string($translation[$requiredField])||trim($translation[$requiredField])===''){$complete=false;break;}

    // Retry each missing record alone so a partial batch cannot silently skip questions.
    $singleError=$generated['error']??null;
    $canRecoverIndividually=$generated['ok']||(($generated['finish']??'')==='MAX_TOKENS');
    if(!$complete&&$canRecoverIndividually){
     for($attempt=0;$attempt<3&&!$complete;$attempt++){
      $singlePrompt="Translate this complete educational item into clear Urdu used in Pakistan. Translate every student-facing field into Urdu script; preserve scientific names, formulas, and symbols. Return exactly one complete result with every required field. ID: $resultKey\n".json_encode([['id'=>$resultKey,'content'=>$entry['content']]],JSON_UNESCAPED_UNICODE);
      $single=geminiJsonRequest($key,$model,$singlePrompt,$entry['fields']);
      if(!$single['ok']){$singleError=$single['error']??'Gemini could not complete this item.';if(str_contains(strtolower($singleError),'rate limit'))break;}
      else{
       $singleResult=normalizeGeminiResult($single['text'],[$resultKey],$entry['fields']);
       $candidate=$singleResult[$resultKey]??null;
       $complete=is_array($candidate);
       if($complete)foreach($entry['fields'] as $requiredField)if(!isset($candidate[$requiredField])||!is_string($candidate[$requiredField])||trim($candidate[$requiredField])===''){$complete=false;break;}
       if($complete)$translation=$candidate;
      }
      if(!$complete&&$attempt<2)usleep(300000*($attempt+1));
     }
    }
    if(!$complete){
     $failed[$resultKey]=$singleError??($generated['error']??'Gemini did not return every translation field. Retry this item.');
     error_log('Urdu translation incomplete for '.$resultKey.'; batch finish: '.($generated['finish']??'error'));
     continue;
    }
    $map=$entry['map'];$id=$entry['id'];$sourceTable=$map['src'];
    if($map['kind']==='mcq'){$s=$conn->prepare("INSERT INTO `{$map['tr']}` (`{$map['trpk']}`,source_id,source_table,question,option_a,option_b,option_c,option_d) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_id=VALUES(source_id),source_table=VALUES(source_table),question=VALUES(question),option_a=VALUES(option_a),option_b=VALUES(option_b),option_c=VALUES(option_c),option_d=VALUES(option_d)");$s->bind_param('iissssss',$id,$id,$sourceTable,$translation['question'],$translation['option_a'],$translation['option_b'],$translation['option_c'],$translation['option_d']);}
    else{$s=$conn->prepare("INSERT INTO `{$map['tr']}` (`{$map['trpk']}`,source_id,source_table,question_text) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE source_id=VALUES(source_id),source_table=VALUES(source_table),question_text=VALUES(question_text)");$s->bind_param('iiss',$id,$id,$sourceTable,$translation['question_text']);}
    if(!$s->execute()){$s->close();urduFail(500,'Unable to save Urdu translation.');}$s->close();$translations[$resultKey]=$translation;
    unset($failed[$resultKey]);
   }
  }
 }
}
echo json_encode(['ok'=>true,'translations'=>$translations,'failed'=>$failed],JSON_UNESCAPED_UNICODE);
