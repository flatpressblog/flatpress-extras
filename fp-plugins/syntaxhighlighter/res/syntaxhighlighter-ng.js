// wrap the content of <pre> elements into <code></code> for prism.js
// as told mentioned in https://prismjs.com/index.html#basic-usage
// Author: DeltaLima
// Date: 21.06.2023
// License: MIT
// 
// Permission is hereby granted, free of charge, to any person obtaining a copy 
// of this software and associated documentation files (the “Software”), to deal
// in the Software without restriction, including without limitation the rights 
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
// copies of the Software, and to permit persons to whom the Software is 
// furnished to do so, subject to the following conditions:
// 
// The above copyright notice and this permission notice shall be included in 
// all copies or substantial portions of the Software.
// 
// THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.



function wrap_pre_tags(used_languages, enable_line_numbers) {
  
  if ( enable_line_numbers == true )
  {
    enable_line_numbers = "line-numbers";
  } else {
    enable_line_numbers = "";
  }
  // iterate through all used_languages
  for (let iUl = 0;iUl < used_languages.length; iUl++)
  {
    // preElements, array of <pre></pre> elements
    // handle [code] without language definition as "none"
    // and we have to look for them up a bit different
    if ( used_languages[iUl] == "" )
    {
      used_languages[iUl] == "none"
      var preElements = document.querySelectorAll("pre:not([class])");
    } else {
      var preElements = document.querySelectorAll("pre." + used_languages[iUl]);
    }

    // iterate through all <pre>
    for (let iEl = 0;iEl < preElements.length; iEl++)
    {
      // put the content of <pre> tag into org_html
      org_html = preElements[iEl].innerHTML;
      // put <code> tag with 'language-' class and plugin-classes
      // prism.js plugin around the <pre> content
      new_html = "<code class=\"language-" + used_languages[iUl] + " " + enable_line_numbers + "\">" + org_html + "</code>";
      // write back our new html and enjoy syntax highlightning :)
      preElements[iEl].innerHTML = new_html;
    }
  }
}
