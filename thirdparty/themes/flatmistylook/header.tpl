<!DOCTYPE html>
<html lang="{$fp_config.locale.lang}">
<head>

	<title>{$flatpress.title|tag:wp_title:'&raquo;'}</title>

    <meta charset="{$flatpress.charset}">	
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

	{action hook=wp_head}
</head>

<body id="section-index">

{widgets pos=top}
<div id="navigation">
<div id="{$id}">
  {$content}
</div>
</div><!-- end id:navigation -->
{/widgets}

<div id="body-container">

  <header id="header">
	<h1><a href="{$smarty.const.BLOG_BASEURL}">{$flatpress.title}</a></h1>
	<p class="subtitle">{$flatpress.subtitle}</p>
  </header> <!-- end of #head -->
        
  <div id="feedarea">
  <dl>
	<dt><strong>Feed on</strong></dt>
	<dd><a href="rss.php">Posts</a></dd>	
  </dl>
  </div><!-- end id:feedarea -->

  
  <div id="headerimage">
  </div><!-- end id:headerimage -->        
        
        
  <div id="outer-container">
