function update_stats()
{
  var body = document.getElementsByTagName('body')[0];
  body.style.cursor = 'wait';
  ajaxGet('ajax-active.php', function()
    {
      if ( window.ajax.readyState == 4 && window.ajax.status == 200 )
      {
        document.getElementById('active-members').innerHTML = ajax.responseText;
      }
    });
  var images = document.getElementsByTagName('img');
  for ( var i = 0; i < images.length; i++ )
  {
    var image = images[i];
    if ( image.className.indexOf('graph') != -1 )
    {
      image.src = (String(image.src)).replace(/&seed=[0-9]+$/, '') + '&seed=' + Math.floor(Math.random() * 100000);
    }
  }
  window.setTimeout(function()
    {
      body.style.cursor = null;
    }, 250);
}

window.onload = function()
{
  var ivl = parseFloat(readCookie('interval'));
  if ( ivl == 0 || isNaN(ivl) )
    ivl = 30.0;
  
  var textbox = document.getElementById('update_ivl');
  textbox.value = String(ivl);
  textbox.onkeyup = process_update_ivl;
  
  ivl = parseInt(ivl * 1000);
  
  window.ajax_update_ivl = window.setInterval('update_stats();', ivl);
}

function set_update_ivl(ivl)
{
  window.clearInterval(ajax_update_ivl);
  createCookie('interval', ivl, 3650);
  ivl = parseInt(ivl * 1000);
  
  window.ajax_update_ivl = window.setInterval('update_stats();', ivl);
}

function process_update_ivl()
{
  var val = parseFloat(this.value);
  
  if ( isNaN(val) || val < 5 )
    val = 10;
  
  set_update_ivl(val);
}

/**
 * Core AJAX library
 */

function ajaxMakeXHR()
{
  var ajax;
  if (window.XMLHttpRequest)
  {
    ajax = new XMLHttpRequest();
  }
  else
  {
    if (window.ActiveXObject)
    {           
      ajax = new ActiveXObject("Microsoft.XMLHTTP");
    }
    else
    {
      return false;
    }
  }
  return ajax;
}

function ajaxGet(uri, f, call_editor_safe) {
  window.ajax = ajaxMakeXHR();
  if ( !ajax )
  {
    return false;
  }
  ajax.onreadystatechange = f;
  ajax.open('GET', uri, true);
  ajax.setRequestHeader( "If-Modified-Since", "Sat, 1 Jan 2000 00:00:00 GMT" );
  ajax.send(null);
}

function ajaxPost(uri, parms, f, call_editor_safe) {
  // Is the editor open?
  window.ajax = ajaxMakeXHR();
  if ( !ajax )
  {
    return false;
  }
  ajax.onreadystatechange = f;
  ajax.open('POST', uri, true);
  ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  // Setting Content-length in Safari triggers a warning
  if ( !is_Safari )
  {
    ajax.setRequestHeader("Content-length", parms.length);
  }
  ajax.setRequestHeader("Connection", "close");
  ajax.send(parms);
}

// Cookie manipulation
function readCookie(name) {var nameEQ = name + "=";var ca = document.cookie.split(';');for(var i=0;i < ca.length;i++){var c = ca[i];while (c.charAt(0)==' ') c = c.substring(1,c.length);if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);}return null;}
function createCookie(name,value,days){if (days){var date = new Date();date.setTime(date.getTime()+(days*24*60*60*1000));var expires = "; expires="+date.toGMTString();}else var expires = "";document.cookie = name+"="+value+expires+"; path=/";}
function eraseCookie(name) {createCookie(name,"",-1);}

