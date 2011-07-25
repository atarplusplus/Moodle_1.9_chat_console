/* JS for chat console logic */

/*
    AJAX based on original code by:

        This is the HTML file for the front-end of the JSON AJAX Driven Chat application
        This code was developed by Ryan Smith of 345 Technical Services

        You may use this code in your own projects as long as this copyright is left
        in place.  All code is provided AS-IS.
        This code is distributed in the hope that it will be useful,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

        For the rest of the code visit http://www.DynamicAJAX.com

        Copyright 2005 Ryan Smith / 345 Technical / 345 Group.
*/

<?php include_once("../../config.php");?>

/* Global UI Variables */
var chat_obj = new Array();
var chat_map = new Array();
var num_chats = 0;
var curr_chat = 0;
var pix_dir = "<?php echo $CFG->wwwroot."/blocks/chat_console/pix/"; ?>";
var main_dir = "<?php echo $CFG->wwwroot."/blocks/chat_console/"; ?>";
var myname = "";
var min_chat_height = 50;
var max_chat_height = 125;


/* Chat Class */

function Chat_Class(index, numSession, element, maxed, 
    myId, yourId,
    sendReq, receiveReq,
    lastMessage, lastPostBy, mTimer)
{
    this.index = index;
    this.numSession = numSession;
    this.element = element;
    this.maxed = maxed;
    this.myId = myId;
    this.yourId = yourId;
    this.sendReq = sendReq;
    this.receiveReq = receiveReq;
    this.lastMessage = lastMessage;
    this.lastPostBy = lastPostBy;
    this.mTimer = mTimer;
}

function GetChatByIndex(index)
{
    for(i in chat_obj)
        if(i.index == index)
            return i;
}

function GetChatOrd(index)
{
    for(i=0;chat_obj[i];i++)
        if(chat_obj[i].index == index)
            return i;
    return -1;
}

/* UI Functions */

function Highlight(set, userid)
{
    switch(set)
    {
        case 1:
            color = "<?php echo $CFG->block_chat_console_highlightcolor; ?>";
            break;
        default:
            color = "white";
    }
    document.getElementById("user"+userid).style.backgroundColor = color;
}

function Open_Div_Chat(name, me, myId, yourId)
{
    if(curr_chat>=3 || Chat_In_Progress(name))
        return;
    myname = me;
    left = 20 + (curr_chat*310);
    leftstr = left+"px";
    chat_obj[num_chats] = new Chat_Class(
        curr_chat, num_chats,
        document.createElement("div"), true,
        myId, yourId,
        getXmlHttpRequestObject(),
        getXmlHttpRequestObject(),
        0, 0, 0);
    chat_obj[num_chats].element = document.createElement("div");
    chat_obj[num_chats].element.setAttribute("id", "chat_div"+num_chats);
    chat_obj[num_chats].element.setAttribute("name", name);
    chat_obj[num_chats].element.style.width = "300px";
    chat_obj[num_chats].element.style.position = "fixed";
    chat_obj[num_chats].element.style.bottom = 0;
    chat_obj[num_chats].element.style.zIndex = "100";
    chat_obj[num_chats].element.style.left = leftstr;
    chat_obj[num_chats].element.innerHTML = Get_Chat_HTML(num_chats, name);
    document.body.appendChild(chat_obj[num_chats].element);
    chat_map[curr_chat] = num_chats;
    InitializeChat(num_chats);
    SetHeight(num_chats);
    num_chats++;
    curr_chat++;
}

function Min_Div_Chat(chat)
{
    if(chat_obj[chat].maxed)
    {
        document.getElementById("chat_text_body"+chat).setAttribute("class","chat_minimized");
        document.getElementById("chat_text_area_body"+chat).setAttribute("class","chat_minimized");
        chat_obj[chat].element.style.height = 45+"px";
        document.getElementById("chat_div"+chat).style.height = 45+"px";
    }
    else
    {
        document.getElementById("chat_text_body"+chat).setAttribute("class","chat_body");
        document.getElementById("chat_text_area_body"+chat).setAttribute("class","chat_body");
        chat_obj[chat].mTimer = setTimeout(function(){getChatText(chat);},0); //Refresh our chat
    }
    chat_obj[chat].maxed = !chat_obj[chat].maxed;
}

//function Max_Div_Chat(chat)
//{
//
//}

function Close_Div_Chat(chat)
{
    var i,j;
    document.body.removeChild(chat_obj[chat].element);
    for(i=chat_obj[chat].index;i < curr_chat-1;i++)
    {
        j=GetChatByIndex(i+1);
        left = 20 + (i*310);
        leftstr = left+"px";
        j.element.style.left = leftstr;
        document.getElementById("chat_div"+j.numSession).style.left = leftstr;
        j.index--;
    }
    chat_obj[chat].index = -1;
    curr_chat--;
}

function Chat_In_Progress(name)
{
    var i;
    for(i=0;i < curr_chat;i++)
        if(chat_obj[chat_map[i]].element.getAttribute("name") == name)
            return true;
    return false;
}


/* Message Handlers */

function Submit_Text(e, chat)
{
    var keynum, ta, t, l;
    if(window.event) // IE
    {
	keynum = e.keyCode;
    }
    else if(e.which) // Netscape/Firefox/Opera
    {
	keynum = e.which;
    }

    if(keynum==13 && e.ctrlKey==0)
    {
        ta = document.getElementById("chat_text_area"+chat).value;
        l = ta.length;
        t = ta.substr(l-1,1);
        if(t == "\n")
            ta = ta.substr(0,l-1);
        document.getElementById("chat_text_area"+chat).value="";

        if(chat_obj[chat].sendReq != null && chat_obj[chat].receiveReq != null)
            sendChatText(chat, ta);
    }

    if(keynum==13 && e.ctrlKey==1)
        document.getElementById("chat_text_area"+chat).value+="<br/>";
}

//Gets the current messages from the server
//Based on (C) 2005 Ryan Smith / 345 Technical / 345 Group.
function getChatText(chat)
{
    if (chat_obj[chat].receiveReq.readyState == 4 || chat_obj[chat].receiveReq.readyState == 0)
    {
        chat_obj[chat].receiveReq.open("GET", main_dir +
            'getChat.php?chat=' + chat_obj[chat].numSession +
            '&last=' + chat_obj[chat].lastMessage +
            '&sender=' + chat_obj[chat].myId +
            '&receiver=' + chat_obj[chat].yourId +
            '&firstopen=0',
            true);
        chat_obj[chat].receiveReq.onreadystatechange = function()
                {
                    handleReceiveChat(chat);
                }
        chat_obj[chat].receiveReq.send(null);
    }
}



/* AJAX Managers */

//Gets the browser specific XmlHttpRequest Object
//Based on (C) 2005 Ryan Smith / 345 Technical / 345 Group.
function getXmlHttpRequestObject()
{
    if (window.XMLHttpRequest)
    {
        return new XMLHttpRequest();
    }
    else if(window.ActiveXObject)
    {
        return new ActiveXObject("Microsoft.XMLHTTP");
    }
    else
    {
        alert('Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.');
        return null;
    }
}

//Initialize the request objects
function InitializeChat(chat)
{
        chat_obj[chat].receiveReq.open("GET", main_dir +
            'getChat.php?chat=' + chat_obj[chat].numSession +
            '&last=' + chat_obj[chat].lastMessage +
            '&sender=' + chat_obj[chat].myId +
            '&receiver=' + chat_obj[chat].yourId +
            '&firstopen=1',
            true);
        chat_obj[chat].receiveReq.onreadystatechange = function()
                {
                    handleReceiveChat(chat);
                }
        chat_obj[chat].receiveReq.send(null);

        chat_obj[chat].sendReq.open("POST", main_dir +
            'getChat.php?chat=' + chat_obj[chat].numSession +
            '&last=' + chat_obj[chat].lastMessage +
            '&sender=' + chat_obj[chat].myId +
            '&receiver=' + chat_obj[chat].yourId,
            true);
        chat_obj[chat].sendReq.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        chat_obj[chat].sendReq.onreadystatechange = function()
            {
                handleSendChat(chat);
            }
        var param = 'message=';
        param += '&chat=' + chat_obj[chat].numSession;
        chat_obj[chat].sendReq.send(param);
}

//Add a message to the chat server.
//Based on (C) 2005 Ryan Smith / 345 Technical / 345 Group.
function sendChatText(chat, text)
{
    if(text == '')
        return;
    if (chat_obj[chat].sendReq.readyState == 4 || chat_obj[chat].sendReq.readyState == 0)
    {
        chat_obj[chat].sendReq.open("POST", main_dir +
            'getChat.php?chat=' + chat_obj[chat].numSession +
            '&last=' + chat_obj[chat].lastMessage +
            '&sender=' + chat_obj[chat].myId +
            '&receiver=' + chat_obj[chat].yourId,
            true);
        chat_obj[chat].sendReq.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        chat_obj[chat].sendReq.onreadystatechange = function()
            {
                handleSendChat(chat);
            }
        var param = 'message=' + text;
        param = param.replace(/[&]/g, '#!amp!#');
        param = param.replace(/[+]/g, '#!pls!#');
        param += '&chat=' + chat_obj[chat].numSession;
        chat_obj[chat].sendReq.send(param);
    }
}

//When our message has been sent, update our page.
//Based on (C) 2005 Ryan Smith / 345 Technical / 345 Group.
function handleSendChat(chat)
{
    //Clear out the existing timer so we don't have
    //multiple timer instances running.
    clearInterval(chat_obj[chat].mTimer);
    getChatText(chat);
}

//Based on (C) 2005 Ryan Smith / 345 Technical / 345 Group.
function handleReceiveChat(chat)
{
    if (chat_obj[chat].receiveReq.readyState == 4)
    {
        var message;
        prevText = document.getElementById("chat_text"+chat).innerHTML;

        //Get the AJAX response and run the JavaScript evaluation function
        //on it to turn it into a useable object.  Notice since we are passing
        //in the JSON value as a string we need to wrap it in parentheses
        var response = eval("(" + chat_obj[chat].receiveReq.responseText + ")");
        for(i=0;i < response.messages.message.length; i++)
        {
            message = response.messages.message[i].text;
            message = message.replace(/(#!amp!#)/g, '&');
            message = message.replace(/(#!pls!#)/g, '+');
            prevText += "<br/>";
            if(chat_obj[chat].lastPostBy != response.messages.message[i].userid)
            {
                prevText += "<strong>"+response.messages.message[i].user+": </strong>";
            }
            //prevText += '&nbsp;&nbsp;<font class="chat_time">' +  response.messages.message[i].time + '</font><br />';
            prevText += message + '<br/>';
            chat_obj[chat].lastMessage = response.messages.message[i].id;
            chat_obj[chat].lastPostBy = response.messages.message[i].userid;
        }

        document.getElementById("chat_text"+chat).innerHTML = prevText;

        SetHeight(chat);

        document.getElementById("chat_text_outer_div"+chat).scrollTop =
            document.getElementById("chat_text_outer_div"+chat).scrollHeight;

        chat_obj[chat].mTimer = setTimeout(function(){getChatText(chat);},2000); //Refresh our chat in 2 seconds
    }
}



/* UI Helpers */

function Get_Chat_HTML(chat_number, name)
{
    image_url = "url('"+pix_dir+"sides.png')";

    chat_html = '';
    chat_html+= '<div>';

    chat_html+= '<div style="margin-top:3px;height:31px">';
    chat_html+= '   <img src="'+pix_dir+'head.png" alt=""/>';
    chat_html+= '<div class="chat_header">';
    chat_html+= '   <table class="chat_header_table">';
    chat_html+= '       <tr>';
    chat_html+= '           <td>';
//    chat_html+= '               <img src="'+pix_dir+'q.png"/>';
    chat_html+= '           </td>';
    chat_html+= '           <td>';
    chat_html+= '               <div class="chat_header_text_attr" id="chat_header_text'+chat_number+'">';
    chat_html+= '                   '+name;
    chat_html+= '               </div>';
    chat_html+= '           </td>';
    chat_html+= '           <td>';
    chat_html+= '               <img id="min" class="min" onmouseover="SwapHover(\'min\',\'min\')" onmouseout="SwapOut(\'min\',\'min\')" src="'+pix_dir+'min.png" alt="Minimize" title="Minimize" onclick="Min_Div_Chat('+chat_number+')"/>';
//    chat_html+= '               <img src="'+pix_dir+'max.png" alt="Pop out" title="Pop out" onclick="Max_Div_Chat('+chat_number+')"/>';
    chat_html+= '               <img id="x" class="x" onmouseover="SwapHover(\'x\',\'x\')" onmouseout="SwapOut(\'x\',\'x\')" src="'+pix_dir+'x.png" alt="Close" title="Close" onclick="Close_Div_Chat('+chat_number+')"/>';
    chat_html+= '           </td>';
    chat_html+= '       </tr>';
    chat_html+= '   </table>';
    chat_html+= '</div>';
    chat_html+= '</div>';

    chat_html+= '<div class="chat_body" id="chat_text_body'+chat_number+'" style="background-image:'+image_url+'">';
    chat_html+= '<div id="chat_text_outer_div'+chat_number+'" class="chat_text_outer_div">';
    chat_html+= '<div id="chat_text'+chat_number+'" class="chat_text_div">';
    chat_html+= '</div>';
    chat_html+= '</div>';
    chat_html+= '</div>';

    chat_html+= '<div class="chat_body" id="chat_text_area_body'+chat_number+'" style="background-image:'+image_url+';height:60px">';
    chat_html+= '<div class="chat_text_area_div">';
    chat_html+= '   <textarea rows="2" cols="1" class="chat_text_area_attr" id="chat_text_area'+chat_number+'" onkeyup="Submit_Text(event,'+chat_number+')"></textarea>';
    chat_html+= '</div>';
    chat_html+= '</div>';

    chat_html+= '<div class="chat_foot_div">';
    chat_html+= '   <img src="'+pix_dir+'foot.png" alt=""/>';
    chat_html+= '</div>';
    chat_html+= '</div>';
    return chat_html;
}

function SetHeight(chat)
{
    var height = min_chat_height;
    var in_height = document.getElementById("chat_text"+chat).offsetHeight;

    if(!chat_obj[chat].maxed)
        return;

    if(in_height > height)
        height = in_height;
    if(height > max_chat_height)
        height = max_chat_height;

    chat_obj[chat].element.style.height = (height+105)+"px";
    document.getElementById("chat_div"+chat).style.height = (height+105)+"px";
    document.getElementById("chat_text_body"+chat).style.height = (height+2)+"px";
    document.getElementById("chat_text_outer_div"+chat).style.height = height+"px";
}

function SwapHover(image,id){ 
	document.getElementById(id).setAttribute('src',pix_dir+image+'_hover.png') 
} 

function SwapOut(image,id){ 
	document.getElementById(id).setAttribute('src',pix_dir+image+'.png') 
} 
