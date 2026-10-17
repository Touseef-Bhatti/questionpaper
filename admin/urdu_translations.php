<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

$config = [
 'mcq:mcqs'=>['table'=>'mcqs','pk'=>'mcq_id','tr'=>'urduMCQs','trpk'=>'mcq_id','src'=>'mcqs','fields'=>['question','option_a','option_b','option_c','option_d'],'title'=>'MCQs'],
 'mcq:mcqs_from_book'=>['table'=>'mcqs_from_book','pk'=>'mcq_id','tr'=>'urduMCQs','trpk'=>'mcq_from_book_id','src'=>'mcqs_from_book','fields'=>['question','option_a','option_b','option_c','option_d'],'title'=>'MCQs from book'],
 'question:questions'=>['table'=>'questions','pk'=>'id','tr'=>'UrduQuestion','trpk'=>'question_id','src'=>'questions','fields'=>['question_text'],'title'=>'Questions'],
 'question:questions_from_book'=>['table'=>'questions_from_book','pk'=>'id','tr'=>'UrduQuestion','trpk'=>'question_from_book_id','src'=>'questions_from_book','fields'=>['question_text'],'title'=>'Questions from book'],
];
$type=($_REQUEST['type']??'mcq')==='question'?'question':'mcq';
$source=(string)($_REQUEST['source']??($type==='mcq'?'mcqs':'questions'));
if(!isset($config[$type.':'.$source])) $source=$type==='mcq'?'mcqs':'questions';
$cfg=$config[$type.':'.$source];
$csrf=generateCSRFToken();

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='next_translation_batch'){
    header('Content-Type: application/json; charset=utf-8');
    if(!verifyCSRFToken((string)($_POST['csrf_token']??''))){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Refresh the page and try again.']);exit;}
    $limit=min(10,max(1,(int)($_POST['limit']??10)));
    $excluded=json_decode((string)($_POST['exclude_ids']??'[]'),true);
    $excluded=is_array($excluded)?array_values(array_unique(array_filter(array_map('intval',$excluded),static fn($id)=>$id>0))):[];
    $excluded=array_slice($excluded,0,1000);
    $excludeSql=$excluded?' AND m.`'.$cfg['pk'].'` NOT IN ('.implode(',',array_fill(0,count($excluded),'?')).')':'';
    $sql="SELECT m.`{$cfg['pk']}` AS id FROM `{$cfg['table']}` m LEFT JOIN `{$cfg['tr']}` u ON u.source_table=? AND u.source_id=m.`{$cfg['pk']}` WHERE u.id IS NULL$excludeSql ORDER BY m.`{$cfg['pk']}` ASC LIMIT ?";
    $stmt=$conn->prepare($sql);
    $types='s'.str_repeat('i',count($excluded)).'i';$params=array_merge([$types,$cfg['src']],$excluded,[$limit]);
    $refs=[];foreach($params as $index=>&$value)$refs[$index]=&$value;call_user_func_array([$stmt,'bind_param'],$refs);
    $stmt->execute();$result=$stmt->get_result();$items=[];while($record=$result->fetch_assoc())$items[]=['kind'=>$type,'source'=>$source,'id'=>(int)$record['id']];$stmt->close();
    echo json_encode(['ok'=>true,'items'=>$items],JSON_UNESCAPED_UNICODE);exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_translation'){
    if(!verifyCSRFToken((string)($_POST['csrf_token']??''))){http_response_code(403);exit('Invalid request token. Refresh and try again.');}
    $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
    if(!$id || $id<1){http_response_code(422);exit('Invalid question ID.');}
    $values=[];foreach($cfg['fields'] as $field){$value=trim((string)($_POST[$field]??''));if($value===''){http_response_code(422);exit('All Urdu fields are required.');}$values[$field]=mb_substr($value,0,12000,'UTF-8');}
    $cols=array_merge([$cfg['trpk'],'source_id','source_table'],$cfg['fields']);$quoted=implode(',',array_map(static fn($c)=>"`$c`",$cols));$placeholders=implode(',',array_fill(0,count($cols),'?'));
    $updates=implode(',',array_map(static fn($f)=>"`$f`=VALUES(`$f`)",$cfg['fields']));
    $sql="INSERT INTO `{$cfg['tr']}` ($quoted) VALUES ($placeholders) ON DUPLICATE KEY UPDATE source_id=VALUES(source_id),source_table=VALUES(source_table),$updates";
    $stmt=$conn->prepare($sql);$types='iis'.str_repeat('s',count($values));$params=[$types,$id,$id,$cfg['src']];foreach($values as $v)$params[]=$v;
    $refs=[];foreach($params as $i=>&$v)$refs[$i]=&$v;call_user_func_array([$stmt,'bind_param'],$refs);
    if(!$stmt->execute()){http_response_code(500);exit('Unable to save this translation.');}$stmt->close();
    header('Location: urdu_translations.php?'.http_build_query(['type'=>$type,'source'=>$source,'status'=>$_POST['status']??'all','q'=>$_POST['q']??'','saved'=>1,'page'=>$_POST['page']??1]));exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete_translation'){
    if(!verifyCSRFToken((string)($_POST['csrf_token']??''))){http_response_code(403);exit('Invalid request token. Refresh and try again.');}
    $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
    if($id && $id>0){$stmt=$conn->prepare("DELETE FROM `{$cfg['tr']}` WHERE source_table=? AND source_id=?");$stmt->bind_param('si',$cfg['src'],$id);$stmt->execute();$stmt->close();}
    header('Location: urdu_translations.php?'.http_build_query(['type'=>$type,'source'=>$source,'status'=>$_POST['status']??'all','q'=>$_POST['q']??'','page'=>$_POST['page']??1,'deleted'=>1]));exit;
}

$status=in_array($_GET['status']??'all',['all','translated','pending'],true)?$_GET['status']??'all':'all';
$search=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$perPage=20;$offset=($page-1)*$perPage;
$originalCols=implode(',',array_map(static fn($f)=>"m.`$f` AS `original_$f`",$cfg['fields']));
$translatedCols=implode(',',array_map(static fn($f)=>"u.`$f` AS `urdu_$f`",$cfg['fields']));
$join="LEFT JOIN `{$cfg['tr']}` u ON u.source_table='".$conn->real_escape_string($cfg['src'])."' AND u.source_id=m.`{$cfg['pk']}`";
$where=[];$params=[];$bindTypes='';
if($status==='pending')$where[]='u.id IS NULL';elseif($status==='translated')$where[]='u.id IS NOT NULL';
if($search!==''){$ors=[];foreach($cfg['fields'] as $field){$ors[]="m.`$field` LIKE ?";$params[]='%'.$search.'%';$bindTypes.='s';} $where[]='('.implode(' OR ',$ors).')';}
$whereSql=$where?' WHERE '.implode(' AND ',$where):'';
$countStmt=$conn->prepare("SELECT COUNT(*) total FROM `{$cfg['table']}` m $join $whereSql");if($params){$refs=[];$refs[]=&$bindTypes;foreach($params as $i=>&$p)$refs[]=&$p;call_user_func_array([$countStmt,'bind_param'],$refs);}$countStmt->execute();$total=(int)$countStmt->get_result()->fetch_assoc()['total'];$countStmt->close();
$listStmt=$conn->prepare("SELECT m.`{$cfg['pk']}` AS source_id,$originalCols,$translatedCols,u.id AS translation_id FROM `{$cfg['table']}` m $join $whereSql ORDER BY m.`{$cfg['pk']}` DESC LIMIT ? OFFSET ?");
$listTypes=$bindTypes.'ii';$listParams=$params;$listParams[]=$perPage;$listParams[]=$offset;$refs=[];$refs[]=&$listTypes;foreach($listParams as $i=>&$p)$refs[]=&$p;call_user_func_array([$listStmt,'bind_param'],$refs);$listStmt->execute();$rows=$listStmt->get_result()->fetch_all(MYSQLI_ASSOC);$listStmt->close();
$pendingStmt=$conn->prepare("SELECT m.`{$cfg['pk']}` AS id FROM `{$cfg['table']}` m $join WHERE u.id IS NULL ORDER BY m.`{$cfg['pk']}` LIMIT 10");$pendingStmt->execute();$batch=[];$pendingResult=$pendingStmt->get_result();while($r=$pendingResult->fetch_assoc())$batch[]=['kind'=>$type,'source'=>$source,'id'=>(int)$r['id']];$pendingStmt->close();
$summary=[];foreach($config as $key=>$item){[$k,$src]=explode(':',$key,2);if($k!==$type)continue;$totalResult=$conn->query("SELECT COUNT(*) c FROM `{$item['table']}`");$all=(int)($totalResult?$totalResult->fetch_assoc()['c']:0);$quotedSource=$conn->real_escape_string($item['src']);$translatedResult=$conn->query("SELECT COUNT(*) c FROM `{$item['tr']}` WHERE source_table='$quotedSource'");$done=(int)($translatedResult?$translatedResult->fetch_assoc()['c']:0);$summary[$src]=['all'=>$all,'done'=>min($done,$all),'title'=>$item['title']];}
$pageCount=max(1,(int)ceil($total/$perPage));
?>
<?php include __DIR__ . '/header.php'; ?>
<main class="ut-admin">
 <header class="ut-hero"><div><span class="ut-kicker">CONTENT TOOLS</span><h1>Urdu Translation Manager</h1><p>Review, translate, and edit Urdu versions of your existing MCQs and questions.</p><a class="ut-test-link" href="urdu_translation_test.php">Run Gemini diagnostics →</a></div><div class="ut-hero-icon" aria-hidden="true">ا ب</div></header>
 <section class="ut-summary">
  <?php foreach($summary as $src=>$stat): ?><a class="ut-summary-card <?= $source===$src?'active':'' ?>" href="?<?= http_build_query(['type'=>$type,'source'=>$src,'status'=>$status]) ?>"><span><?= htmlspecialchars($stat['title']) ?></span><strong><?= number_format($stat['done']) ?><small> / <?= number_format($stat['all']) ?> translated</small></strong><i><b style="width:<?= $stat['all']?round(100*$stat['done']/$stat['all']):0 ?>%"></b></i></a><?php endforeach; ?>
 </section>
 <section class="ut-panel">
  <form method="get" class="ut-filters">
   <label>Content type<select name="type" onchange="this.form.submit()"><option value="mcq" <?= $type==='mcq'?'selected':'' ?>>MCQs</option><option value="question" <?= $type==='question'?'selected':'' ?>>Short and long questions</option></select></label>
   <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
   <label>Status<select name="status"><option value="all" <?= $status==='all'?'selected':'' ?>>All</option><option value="pending" <?= $status==='pending'?'selected':'' ?>>Needs translation</option><option value="translated" <?= $status==='translated'?'selected':'' ?>>Translated</option></select></label>
   <label class="ut-search">Find in original content<input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search question text"></label>
   <button class="ut-btn secondary" type="submit">Apply filters</button>
  </form>
  <div class="ut-toolbar"><div><strong><?= number_format($total) ?></strong> records <span>· page <?= $page ?> of <?= $pageCount ?></span></div><div class="ut-batch-controls"><label>Translate total<select id="batchTarget"><option value="10">10</option><option value="20">20</option><option value="50">50</option><option value="100">100</option><option value="120">120</option><option value="manual">Custom</option></select></label><label id="customTargetWrap" hidden>Custom count<input id="customTarget" type="number" min="1" max="1000" value="10" inputmode="numeric"></label><button class="ut-btn primary" id="translateBatch" <?= !$batch?'disabled':'' ?>>Start translation</button></div></div>
  <?php if(isset($_GET['saved'])): ?><div class="ut-alert success">Translation saved.</div><?php elseif(isset($_GET['deleted'])): ?><div class="ut-alert success">Translation removed. It will be included in the next pending batch.</div><?php endif; ?>
  <div class="ut-progress" id="translationProgress" hidden><div class="ut-progress-head"><span id="progressText">Preparing translations...</span><span id="progressPercent">0%</span></div><div class="ut-progress-track" role="progressbar" aria-label="Urdu translation progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div><div class="ut-progress-detail" id="progressDetail">0 items completed</div></div>
  <div class="ut-table-wrap"><table class="ut-table"><thead><tr><th>Original content</th><th>Urdu translation <small>Editable</small></th><th>Record</th></tr></thead><tbody>
   <?php foreach($rows as $row): ?><tr>
    <td class="ut-original"><?php if($type==='mcq'): ?><strong><?= htmlspecialchars($row['original_question']) ?></strong><?php foreach(['option_a'=>'A','option_b'=>'B','option_c'=>'C','option_d'=>'D'] as $field=>$label): ?><p><b><?= $label ?>)</b> <?= htmlspecialchars($row['original_'.$field]) ?></p><?php endforeach; ?><?php else: ?><?= nl2br(htmlspecialchars($row['original_question_text'])) ?><?php endif; ?></td>
    <td><form method="post" class="ut-edit-form"><input type="hidden" name="action" value="save_translation"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['source_id'] ?>"><input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>"><input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>"><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><input type="hidden" name="page" value="<?= $page ?>">
     <?php foreach($cfg['fields'] as $field): ?><label class="ut-translation-field"><span><?= $field==='question_text'?'Question':($field==='question'?'Question':strtoupper(str_replace('option_','Option ', $field))) ?></span><textarea name="<?= $field ?>" rows="<?= $field==='question_text'||$field==='question'?3:2 ?>" required placeholder="Urdu text…"><?= htmlspecialchars((string)($row['urdu_'.$field]??'')) ?></textarea></label><?php endforeach; ?><div class="ut-row-actions"><span class="ut-status <?= $row['translation_id']?'translated':'pending' ?>"><?= $row['translation_id']?'Translated':'Pending' ?></span><button class="ut-btn secondary" type="submit">Save translation</button></div>
    </form></td><td><span class="ut-id">#<?= (int)$row['source_id'] ?></span><small><?= htmlspecialchars($cfg['title']) ?></small><button type="button" class="ut-btn secondary ut-single-translate" data-kind="<?= htmlspecialchars($type) ?>" data-source="<?= htmlspecialchars($source) ?>" data-id="<?= (int)$row['source_id'] ?>" data-force="<?= $row['translation_id']?'1':'0' ?>" style="margin-top:12px"><?= $row['translation_id']?'Regenerate translation':'Translate this item' ?></button><?php if($row['translation_id']): ?><form method="post" onsubmit="return confirm('Delete this Urdu translation?')"><input type="hidden" name="action" value="delete_translation"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['source_id'] ?>"><input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>"><input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>"><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><input type="hidden" name="page" value="<?= $page ?>"><button class="ut-btn secondary" type="submit" style="margin-top:12px">Delete translation</button></form><?php endif; ?></td>
   </tr><?php endforeach; ?>
   <?php if(!$rows): ?><tr><td colspan="3" class="ut-empty">No matching questions found.</td></tr><?php endif; ?>
  </tbody></table></div>
  <nav class="ut-pagination" aria-label="Pagination"><?php if($page>1): ?><a href="?<?= http_build_query(['type'=>$type,'source'=>$source,'status'=>$status,'q'=>$search,'page'=>$page-1]) ?>">← Previous</a><?php endif; ?><span><?= $page ?> / <?= $pageCount ?></span><?php if($page<$pageCount): ?><a href="?<?= http_build_query(['type'=>$type,'source'=>$source,'status'=>$status,'q'=>$search,'page'=>$page+1]) ?>">Next →</a><?php endif; ?></nav>
 </section>
</main>
<style>
.ut-test-link{display:inline-block;margin-top:12px;color:#4338ca;text-decoration:none;font-size:13px;font-weight:700}.ut-admin{max-width:1320px;margin:32px auto;padding:0 22px 50px;color:#172033}.ut-hero{display:flex;justify-content:space-between;align-items:center;padding:28px 32px;border-radius:18px;background:linear-gradient(120deg,#eff6ff,#f5f3ff);border:1px solid #dbeafe}.ut-kicker{font-size:11px;letter-spacing:.14em;font-weight:800;color:#4f46e5}.ut-hero h1{margin:7px 0;font-size:30px;color:#172554}.ut-hero p{margin:0;color:#526078}.ut-hero-icon{font-size:34px;color:#4338ca;background:#fff;border-radius:16px;padding:16px 20px;box-shadow:0 4px 14px #17255412}.ut-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:18px 0}.ut-summary-card{padding:18px 20px;background:white;border:1px solid #e2e8f0;border-radius:14px;color:inherit;text-decoration:none}.ut-summary-card.active{border-color:#6366f1;box-shadow:0 0 0 2px #6366f122}.ut-summary-card>span{display:block;color:#64748b;font-size:13px}.ut-summary-card strong{display:block;margin:7px 0;font-size:24px}.ut-summary-card small{font-size:12px;font-weight:500;color:#64748b}.ut-summary-card i{height:5px;display:block;background:#e2e8f0;border-radius:10px;overflow:hidden}.ut-summary-card i b{height:100%;display:block;background:#4f46e5}.ut-panel{padding:20px;background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 8px 24px #0f172a08}.ut-filters{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.ut-filters label{display:grid;gap:6px;font-size:12px;font-weight:700;color:#475569}.ut-filters input,.ut-filters select,.ut-translation-field textarea{font:inherit;color:#172033;border:1px solid #cbd5e1;border-radius:9px;padding:10px 11px;background:white}.ut-search{flex:1;min-width:190px}.ut-btn{font:inherit;font-size:13px;font-weight:700;border:1px solid #cbd5e1;border-radius:9px;padding:10px 14px;cursor:pointer;background:#fff;color:#334155}.ut-btn.primary{background:#4338ca;color:white;border-color:#4338ca}.ut-btn:disabled{opacity:.5;cursor:not-allowed}.ut-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:20px 0 14px;padding-top:14px;border-top:1px solid #e2e8f0}.ut-toolbar span{font-size:13px;color:#64748b}.ut-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}.ut-table{width:100%;min-width:900px;border-collapse:collapse}.ut-table th{padding:12px 14px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc}.ut-table th small{display:block;text-transform:none;font-weight:500;letter-spacing:0}.ut-table td{vertical-align:top;padding:14px;border-top:1px solid #e2e8f0}.ut-original{max-width:330px;line-height:1.55;font-size:13px}.ut-original p{margin:5px 0;color:#475569}.ut-edit-form{min-width:300px}.ut-translation-field{display:grid;gap:5px;margin-bottom:8px}.ut-translation-field span{font-size:11px;font-weight:700;color:#64748b}.ut-translation-field textarea{width:100%;min-height:52px;resize:vertical;line-height:1.7;font-family:'Noto Nastaliq Urdu','Noto Naskh Arabic',Arial,serif;direction:rtl;text-align:right;box-sizing:border-box}.ut-row-actions{display:flex;align-items:center;justify-content:space-between;gap:8px}.ut-status{font-size:11px;border-radius:999px;padding:4px 8px}.ut-status.translated{background:#dcfce7;color:#166534}.ut-status.pending{background:#fff7ed;color:#9a3412}.ut-id{font-weight:700}.ut-id+small{display:block;margin-top:5px;color:#64748b}.ut-empty{text-align:center;padding:36px!important;color:#64748b}.ut-pagination{display:flex;justify-content:center;align-items:center;gap:22px;padding:18px}.ut-pagination a{color:#4338ca;text-decoration:none;font-weight:700}.ut-alert{padding:10px 12px;border-radius:9px;margin:10px 0}.ut-alert.success{background:#dcfce7;color:#166534}.ut-progress{margin:16px 0;padding:14px 16px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0}.ut-progress-head{display:flex;justify-content:space-between;gap:12px;font-size:13px;margin-bottom:9px}.ut-progress-track{height:8px;background:#e2e8f0;border-radius:10px;overflow:hidden}.ut-progress-track span{display:block;width:0;height:100%;border-radius:10px;background:linear-gradient(90deg,#4f46e5,#38bdf8);transition:width .25s ease}.ut-progress-detail{margin-top:8px;color:#64748b;font-size:12px;font-variant-numeric:tabular-nums}@media(max-width:650px){.ut-admin{margin:18px auto;padding:0 12px 28px}.ut-hero{padding:20px}.ut-hero h1{font-size:23px}.ut-hero-icon{font-size:25px;padding:12px}.ut-summary{grid-template-columns:1fr}.ut-panel{padding:13px}.ut-filters>*{width:100%}.ut-filters label{width:100%}.ut-toolbar{align-items:stretch;flex-direction:column}.ut-toolbar .ut-btn{width:100%}.ut-table{min-width:720px}}
</style>
<style>
.ut-batch-controls{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.ut-batch-controls label{display:grid;gap:5px;font-size:12px;font-weight:700;color:#475569}.ut-batch-controls select,.ut-batch-controls input{min-width:100px;font:inherit;color:#172033;border:1px solid #cbd5e1;border-radius:9px;padding:10px 11px;background:#fff}.ut-batch-controls input{width:120px;box-sizing:border-box}.ut-batch-controls [hidden]{display:none!important}
@media(max-width:650px){.ut-batch-controls{width:100%;display:grid;grid-template-columns:1fr 1fr}.ut-batch-controls label,.ut-batch-controls select,.ut-batch-controls input,.ut-batch-controls .ut-btn{width:100%;box-sizing:border-box}.ut-batch-controls #translateBatch{grid-column:1/-1}}
</style>
<script>
const urduTranslationCsrf=<?= json_encode($_SESSION['urdu_translation_csrf']??=bin2hex(random_bytes(32))) ?>;
const adminCsrf=<?= json_encode($csrf) ?>;
const batchTarget=document.getElementById('batchTarget');
const customTargetWrap=document.getElementById('customTargetWrap');
batchTarget?.addEventListener('change',()=>{customTargetWrap.hidden=batchTarget.value!=='manual';});

function updateProgress(done,total,label,detail){
    const bar=document.querySelector('#translationProgress .ut-progress-track span');
    const track=document.querySelector('#translationProgress .ut-progress-track');
    const safeTotal=Math.max(1,Number(total)||1),safeDone=Math.max(0,Math.min(Number(done)||0,safeTotal));
    const percent=Math.round(safeDone/safeTotal*100);
    document.getElementById('progressText').textContent=label;
    document.getElementById('progressPercent').textContent=`${percent}%`;
    document.getElementById('progressDetail').textContent=detail||`${safeDone} of ${safeTotal} items completed`;
    if(track)track.setAttribute('aria-valuenow',String(percent));
    if(bar)bar.style.width=`${percent}%`;
}

document.querySelectorAll('.ut-single-translate').forEach(button=>button.addEventListener('click',async()=>{
    const progress=document.getElementById('translationProgress'),row=button.closest('tr'),form=row?.querySelector('.ut-edit-form');
    button.disabled=true;progress.hidden=false;
    try{
        const item={kind:button.dataset.kind,source:button.dataset.source,id:Number(button.dataset.id)};
        const key=`${item.kind}:${item.source}:${item.id}`;
        const response=await fetch('../api/urdu_translate.php',{method:'POST',headers:{'Content-Type':'application/json','X-Urdu-CSRF':urduTranslationCsrf},body:JSON.stringify({items:[item],force:button.dataset.force==='1'})});
        const payload=await response.json();
        if(!response.ok||!payload.ok)throw new Error(payload.error||'Translation failed');
        const translated=payload.translations?.[key];
        if(!translated)throw new Error(payload.failed?.[key]||'Gemini could not complete this item.');
        form?.querySelectorAll('textarea').forEach(field=>{if(typeof translated[field.name]==='string')field.value=translated[field.name];});
        const status=row?.querySelector('.ut-status');
        if(status){status.textContent='Translated';status.className='ut-status translated';}
        button.dataset.force='1';button.textContent='Regenerate translation';button.disabled=false;
        updateProgress(1,1,'Translation saved and ready.','Single item · 1 of 1 completed');
    }catch(error){document.getElementById('progressText').textContent=error.message;document.getElementById('progressPercent').textContent='Could not complete';button.disabled=false;}
}));

document.getElementById('translateBatch')?.addEventListener('click',async function(){
    const button=this,progress=document.getElementById('translationProgress');
    const selected=batchTarget.value==='manual'?Number(document.getElementById('customTarget').value):Number(batchTarget.value);
    if(!Number.isInteger(selected)||selected<1||selected>1000){document.getElementById('progressText').textContent='Enter a total between 1 and 1,000.';progress.hidden=false;return;}
    button.disabled=true;batchTarget.disabled=true;document.getElementById('customTarget').disabled=true;progress.hidden=false;
    let attempted=0,saved=0,failed=0,batchesCompleted=0;const totalBatches=Math.ceil(selected/10),excluded=new Set();
    updateProgress(0,selected,'Starting translation batches…',`Batch 0 of ${totalBatches} · 0 of ${selected} items completed`);
    try{
        while(attempted<selected){
            const limit=Math.min(10,selected-attempted);
            const form=new FormData();form.set('action','next_translation_batch');form.set('csrf_token',adminCsrf);form.set('limit',String(limit));form.set('exclude_ids',JSON.stringify(Array.from(excluded)));
            const queueResponse=await fetch(window.location.href,{method:'POST',body:form,credentials:'same-origin'});
            const queue=await queueResponse.json();
            if(!queueResponse.ok||!queue.ok)throw new Error(queue.error||'Could not load the next pending batch.');
            if(!queue.items?.length)break;
            queue.items.forEach(item=>excluded.add(Number(item.id)));
            const batchNumber=batchesCompleted+1;
            updateProgress(attempted,selected,`Translating batch ${batchNumber} of ${totalBatches}…`,`Batch ${batchNumber} of ${totalBatches} · ${attempted} of ${selected} items completed`);
            const translationResponse=await fetch('../api/urdu_translate.php',{method:'POST',headers:{'Content-Type':'application/json','X-Urdu-CSRF':urduTranslationCsrf},body:JSON.stringify({items:queue.items})});
            const result=await translationResponse.json();
            if(!translationResponse.ok||!result.ok)throw new Error(result.error||'Translation batch failed.');
            const batchSaved=Object.keys(result.translations||{}).length;
            const batchFailed=Object.keys(result.failed||{}).length;
            attempted+=queue.items.length;saved+=batchSaved;failed+=batchFailed+Math.max(0,queue.items.length-batchSaved-batchFailed);
            batchesCompleted++;
            updateProgress(attempted,selected,`Batch ${batchesCompleted} of ${totalBatches} complete.`,`${attempted} of ${selected} items completed · ${saved} saved · ${failed} need retry`);
            if(batchFailed>=queue.items.length)break;
        }
        const message=attempted===0?'No pending translations remain.':`${saved} saved; ${failed} need retry after ${attempted} item(s).`;
        updateProgress(attempted,selected,message,`Batch ${batchesCompleted} of ${totalBatches} complete · ${attempted} of ${selected} items completed`);
        if(attempted>0)setTimeout(()=>location.reload(),1400);
        else{button.disabled=false;batchTarget.disabled=false;document.getElementById('customTarget').disabled=false;}
    }catch(error){document.getElementById('progressText').textContent=error.message;document.getElementById('progressPercent').textContent='Could not complete';button.disabled=false;batchTarget.disabled=false;document.getElementById('customTarget').disabled=false;}
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
