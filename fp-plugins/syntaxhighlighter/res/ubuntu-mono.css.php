<?php header('Content-Type: text/css; charset=utf-8'); ?>
@charset "UTF-8";

<?php
// Turn off all error reporting
error_reporting(0);

// PHP4.1.0 or later supported
if (phpversion() >= "4.1.0") {
  extract($_GET);
}

// Generate the URL of the CSS file
$host = (empty ($_SERVER ['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]";
$hostupper = strtoupper($host);
$path = rtrim(dirname($_SERVER ['PHP_SELF']), '/\\');
$url = '' . $host . $path . '/';
?>

/*
 * ================
 * Font Ubuntu Mono
 * ================
 *
 * Name: Font Ubuntu Mono
 * Module: ubuntu-mono.css
 * Designer Name: Dalton Maag
 * Author URI: https://fonts.google.com/specimen/Ubuntu+Mono
 * Description: This file defines the font
 * Last change: 05.09.2023
 * License: The font of Google "Ubuntu Mono" is under SIL Open Font-License (OFL)
 */

/* cyrillic-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-italic-cyrillic-ext'),
  url('<?php echo $url;?>ubuntu-mono/cyrillic-ext-400-italic.woff2') format('woff2');
  unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}

/* cyrillic */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-italic-cyrillic'),
  url('<?php echo $url;?>ubuntu-mono/cyrillic-400-italic.woff2') format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}

/* greek-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-italic-greek-ext'),
  url('<?php echo $url;?>ubuntu-mono/greek-ext-400-italic.woff2') format('woff2');
  unicode-range: U+1F00-1FFF;
}

/* greek */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-italic-greek'),
  url('<?php echo $url;?>ubuntu-mono/greek-400-italic.woff2') format('woff2');
  unicode-range: U+0370-03FF;
}

/* latin-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-italic-latin-ext'),
  url('<?php echo $url;?>ubuntu-mono/latin-ext-400-italic.woff2') format('woff2');
  unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
}

/* latin */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-italic-latin'),
  url('<?php echo $url;?>ubuntu-mono/latin-400-italic.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

/* cyrillic-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-italic-cyrillic-ext'),
  url('<?php echo $url;?>ubuntu-mono/cyrillic-ext-700-italic.woff2') format('woff2');
  unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}

/* cyrillic */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-italic-cyrillic'),
  url('<?php echo $url;?>ubuntu-mono/cyrillic-700-italic.woff2') format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}

/* greek-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-italic-greek-ext'),
  url('<?php echo $url;?>ubuntu-mono/greek-ext-700-italic.woff2') format('woff2');
  unicode-range: U+1F00-1FFF;
}

/* greek */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-italic-greek'),
  url('<?php echo $url;?>ubuntu-mono/greek-700-italic.woff2') format('woff2');
  unicode-range: U+0370-03FF;
}

/* latin-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-italic-latin-ext'),
  url('<?php echo $url;?>ubuntu-mono/latin-ext-700-italic.woff2') format('woff2');
  unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
}

/* latin */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: italic;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-italic-latin'),
  url('<?php echo $url;?>ubuntu-mono/latin-700-italic.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

/* cyrillic-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-normal-cyrillic-ext'),
  url('<?php echo $url;?>ubuntu-mono/cyrillic-ext-400-normal.woff2') format('woff2');
  unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}

/* cyrillic */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-normal-cyrillic'),
  url('<?php echo $url;?>ubuntu-mono/cyrillic-400-normal.woff2') format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}

/* greek-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-normal-greek-ext'),
  url('<?php echo $url;?>ubuntu-mono/greek-ext-400-normal.woff2') format('woff2');
  unicode-range: U+1F00-1FFF;
}

/* greek */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-normal-greek'),
  url('<?php echo $url;?>ubuntu-mono/greek-400-normal.woff2') format('woff2');
  unicode-range: U+0370-03FF;
}

/* latin-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-normal-latin-ext'),
  url('<?php echo $url;?>ubuntu-mono/latin-ext-400-normal.woff2') format('woff2');
  unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
}

/* latin */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: local('UbuntuMono-Regular-normal-latin'),
  url('<?php echo $url;?>ubuntu-mono/latin-400-normal.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

/* cyrillic-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-normal-cyrillic-ext'),
  url('<?php echo $url;?>ubuntu-mono/cyrillic-ext-700-normal.woff2') format('woff2');
  unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}

/* cyrillic */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-normal-cyrillic'),
  url('<?php echo $url;?>ubuntu-mono/cyrillic-700-normal.woff2') format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}

/* greek-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-normal-greek-ext'),
  url('<?php echo $url;?>ubuntu-mono/greek-ext-700-normal.woff2') format('woff2');
  unicode-range: U+1F00-1FFF;
}

/* greek */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-normal-greek'),
  url('<?php echo $url;?>ubuntu-mono/greek-700-normal.woff2') format('woff2');
  unicode-range: U+0370-03FF;
}

/* latin-ext */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-normal-latin-ext'),
  url('<?php echo $url;?>ubuntu-mono/latin-ext-700-normal.woff2') format('woff2');
  unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
}

/* latin */
@font-face {
  font-family: "Ubuntu Mono";
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: local('UbuntuMono-Bold-normal-latin'),
  url('<?php echo $url;?>ubuntu-mono/latin-700-normal.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
