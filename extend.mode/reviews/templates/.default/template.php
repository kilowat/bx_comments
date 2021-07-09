<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>
<?

if (!function_exists('recurse_comment')) {
    function recurse_comment($arr)
    {
        $html = '<ul>';
        foreach ($arr as $item) {
            $html.='<li>';
                $html.='<div class="user-name">'.$item['name'].'</div>';
                $html.='<div class="comment-date">'.$item['date'].'</div>';
                $html.='<div class="comment">'.$item['text'].'</div>';
            $html.='<li>';
            if (is_array($item['comments']) && count($item['comments']) > 0) {
                $html.=recurse_comment($item['comments']);
            }
        }
        $html.='</ul>';
        return $html;
    }
}

?>

<reviews
  params-id='<?=$arResult['params_id']?>'
  json-params='<?=json_encode($arParams)?>'>
    <div style="display: none">
        <?echo recurse_comment($arResult['comments'])?>
    </div>
</reviews>


