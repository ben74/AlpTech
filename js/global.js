/*
todo: Mixted Promises achieving common Goal
&& various ajax requests interceptions and loggings
recup function from perron :: global.js
*/
const start = Date.now();
var x,loaded={},dt='',cl=console.log,hostname='https://www.hostname.com';
if(getCookie('ngrok'))hostname=getCookie('ngrok');//put into powr cookies
cl('hostname:'+hostname);

document.addEventListener('readystatechange',function(e){if(document.readyState=='complete'){squareResize('drsC');}});
window.addEventListener('DOMContentLoaded',function(){squareResize('dcl');});
window.addEventListener('load',function(){squareResize('load');});//Fires !!!

if (!document.querySelector('#powercss')) {
    var link = document.createElement('link');link.id = 'powercss';link.rel = 'stylesheet';link.type = 'text/css';link.media = 'all';
    //link.href = '//'+hostname'/misc/powr.php?r=css';
    link.href = hostname+'/misc/powr.php?r=css#'+hostname;   
    link.onload = function(){loaded['css']=Date.now() - start;};
    document.head.appendChild(link);
}

//if (0 && !document.querySelector('#ccjs')) {var link = document.createElement('script');link.id = 'ccjs';link.type = 'text/javascript';link.src = hostname+'/misc/powr.php?r=js2#'+hostname;document.head.appendChild(link);}//Secondary for dev

floaded.t=0
function floaded(e){
    if(floaded.t)return;floaded.t=1;
    //console.log('domloaded');
    x=document.querySelectorAll('#appView .socialFeed.grid .postItem');
    dt=x[0].getAttribute('data-type').toLowerCase();
    document.body.classList.add(dt);
    //console.log(dt,x);
    txt='Voir le post';
    if(/Youtube/i.test(dt)){txt='Voir la vidéo';}
    //+ récup du lien par dessus
    x.forEach(function(y){
        link=y.getAttribute('data-link');//cl(y,link);
        y.style.height =y.offsetWidth+'px';
        y.innerHTML='<div class="sw-tile-texte"><div class="wrapper_content_vertical_middle"><div class="content_vertical_middle"><div class="texte"></div><a href="'+link+'" target="_blank" class="sw-tile-link readmore link_more">'+txt+'</a></div></div></div>'+y.innerHTML;
    });

    x=document.querySelectorAll("[style='font-size: 14px;']");//twitter not good
    x.forEach(function(y){y.removeAttribute('style');});
//powr: 2 191 130
/** are the css operations done ? look for el.computedStyle **/
    squareResize(1);//Not fully loaded yet ..
    setTimeout('squareResize(100)',100);//enough =)
    setTimeout('squareResize(2000)',2000);
    setTimeout('squareResize(5000)',5000);
//Puis on applique le style :) depends when applied ..
}

function squareResize(z){
    //cl('powr:squareResize',x,dt);
    //cl('powr:',dt,z,'real:',Date.now() - start,'css:',loaded['css']);
    if(/facebook|twitter/i.test(dt)){
        x=document.querySelectorAll('.postItem');
        x.forEach(function(el){
            var h=el.clientHeight,w=el.clientWidth;           
            if((h+2) < w){
                console.log('powr:',z,'w:',w,'h:',h,'real:',Date.now() - start,'css:',loaded['css'],el);
                el.setAttribute('style','height:'+w+'px');
            }
        });
    }
}

function defer(conditionsCallback,launch,ms){//#appView .socialFeed.grid 
    var ok=conditionsCallback(),ms=ms||100;
    if(!ok){setTimeout('defer('+conditionsCallback+','+launch+')',ms);}//Defer while loading
    else{launch();cl('powr:ok');}//do
}//Defferer

defer(
    function(){var ok=(document.readyState=='complete' && typeof window['jQuery'] == 'function' && document.querySelectorAll('.postItem').length>0);return ok;},
    function(){floaded();},
    100
);

function getCookie(c){var i,x,y,a=document.cookie.split(';');for(i=0;i<a.length;i++){x=a[i].substr(0,a[i].indexOf('='));y=a[i].substr(a[i].indexOf('=')+1);x=x.replace(/^\s+|\s+$/g,'');if(x==c){return unescape(y);}}return 0;}/*getcookie*/

