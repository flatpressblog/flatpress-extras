# syntaxhighlighter-ng

Origin: https://git.la10cy.net/DeltaLima/flatpress-plugin-syntaxhighlighter-ng

Demo: https://deltalima.org/blog/index.php/syntaxhighlighter-ng-testpage/

based on the original FlatPress plugin [syntaxhighlighter from 2005](https://forum.flatpress.org/viewtopic.php?p=1130&hilit=syntax+highlight#p1135), updated in 2023 to prism.js

## installation

Download the [latest release](https://github.com/Fraenkiman/fp-plugins/archive/refs/heads/main.zip) and extract `main.zip` to your `fp-plugins/` folder.

If you want to use git, use
```shell
$ git@github.com:Fraenkiman/fp-plugins.git /pathto/flatpress/fp-plugins/
```

## codeblock with language syntax highlightning

When you just create an `[code][/code]` block, then there will be no syntax highlightning.

To enable it, you have to specify the language you want to get highlighted, for example:

```
[code=bash]
if [ "$1" == "bash" ] 
then
  echo "Yeah :)"
else 
  echo "something else"
fi
[/code]
```
For all language tags see https://prismjs.com/#supported-languages
Not all listed languages are available by default, please see the configuration below!

# configuration

You can configure the used size of prismjs and it's theme. For that just edit `config.php` and set your favorite. 

The default values are `small` for size (see available languages below) and `okaidia` for the theme.

```php
<?php
/*
 * size: tiny, small, full
 *
 *        tiny: 21KB (Markup, HTML, XML, SVG, MathML, SSML, Atom, RSS, CSS, C-like, JavaScript)
 *
 *        small: 106KB (markup, css, clike, javascript, apacheconf, arduino, bash, basic, batch, 
 *                    bbcode, c, cpp, cmake, csv, diff, docker, git, go, http, ini, java, json,
 *                    log, makefile, markdown, markup-templating, nginx, pascal, perl, php,
 *                    powershell, python, ruby, shell-session, sql, typescript, vbnet,
 *                    visual-basic, wiki, yaml)
 *
 *        full: 567KB (see https://prismjs.com/index.html#supported-languages for list of supported languages)
 *
 * theme: coy, dark, default, funky, okaidia, solarizedlight, tomorrow, twilight
 * 
 * line-numbers: 'true' or 'false' (Do show line numbers or not)
 *       
 */

return [
        // change here
        'size' => 'small',
        'theme' => 'okaidia',
        'line-numbers' => 'true',
]
?>
```
