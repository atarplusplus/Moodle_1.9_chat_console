/* Javascript for chat block content. */

<?php include_once("../../config.php");?>

var main_dir = "<?php echo $CFG->wwwroot."/blocks/chat_console/"; ?>";
var block_timer = setTimeout(function(){RefreshBlock();},0);
var blockReq = getXmlHttpRequestObject();
var messageReq = getXmlHttpRequestObject();
var firstTime = true;
var lastMessage = null;

function RefreshBlock()
{
    if (blockReq.readyState == 4 || blockReq.readyState == 0)
    {
        blockReq.open("GET", 
            main_dir + 'content.php',
            true);
        blockReq.onreadystatechange = function()
            {
                if(blockReq.responseText != "")
                {
                    document.getElementById("chat_console_block").innerHTML =
                        blockReq.responseText;
                }
            }
        blockReq.send(null);
    }
    if (messageReq.readyState == 4 || messageReq.readyState == 0)
    {
        messageReq.open("GET",
            main_dir + 'getMessageStatus.php?myId=' + myId,
            true);
        messageReq.onreadystatechange = function()
            {
                if (messageReq.readyState == 4)
                {
                    var response = eval("(" + messageReq.responseText + ")");
                    if(!firstTime)
                        HandleAutoChat(response.messages);
                    lastMessage = response.messages;
                }
            }
        messageReq.send(null);
    }

    block_timer = setTimeout(function(){RefreshBlock();},1000*5);
    firstTime = false;
}

function HandleAutoChat(response)
{
    var i;
    if(lastMessage == null)
        return;
    if(lastMessage.length == response.length)
        return;
    for(i=lastMessage.length; i < response.length; i++)
        Open_Div_Chat(response[i].name,
            response[i].me,
            response[i].myId,
            response[i].yourId);
}


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
