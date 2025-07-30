<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DaddyLiveHD - Match Schedule - WatchIt</title>
    <meta name="description" content="Watch today's live match schedule only on WatchIt DaddyLiveHD – Watch Live Sports Streaming Free, Daily Schedule Online TV Channels, Boxing & MMA, UFC, MBA, MLB, NHL, NFL, Soccer, 24/7 updated live stream,.">
    <meta name="keywords" content="DaddyLiveHD,live sports, match schedule, daddylive, watchit, Watch, Live, Sports, Streaming, Free, Daily Schedule Online TV Channels, Boxing & MMA, UFC, MBA, MLB, NHL, NFL, Soccer, 24/7 updated live stream">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <link rel="icon" type="image/png" href="https://www.svgrepo.com/show/508699/football-soccer.svg">

    <style>
        body {
            background-color: #000;
            color: #ff6739;
            margin: 0;
            padding: 0;
        }

        .iframe-wrapper {
            position: relative;
            width: 100%;
            max-width: 100%;
            margin: auto;
        }

        #liveFrame {
            width: 100%;
            height: 2500px;
            border: none;
            display: block;
        }

        #controls {
            margin: 10px 0;
            text-align: center;
        }

        #controls input {
            width: 80px;
            padding: 5px;
            margin: 0 5px;
        }

        #reloadBtn {
            padding: 6px 15px;
            background-color: #1e88e5;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        #reloadBtn:hover {
            background-color: #1565c0;
        }
    </style>

    <script>
        document.addEventListener("keydown", function(e) {
            if (
                (e.ctrlKey && e.shiftKey && e.keyCode == "I".charCodeAt(0)) ||
                (e.ctrlKey && e.shiftKey && e.keyCode == "J".charCodeAt(0)) ||
                (e.ctrlKey && e.keyCode == "U".charCodeAt(0)) ||
                (e.keyCode == 123)
            ) {
                e.preventDefault();
                return false;
            }
        });
        document.addEventListener("contextmenu", function(e) {
            e.preventDefault();
        });
    </script>
</head>

<body>

<div class="iframe-wrapper">
    <div id="controls">
        🔁
        <button id="reloadBtn" onclick="reloadIframe()">Reload</button>
         | 
        📏 Length: <input type="number" id="iframeHeight" value="1500" onchange="updateIframeSize()"> px
         
        ↔️ Width: <input type="text" id="iframeWidth" value="100%" onchange="updateIframeSize()">
    </div>

    <iframe id="liveFrame" src="https://watchit.my/iframe.php?u=L3NjaGVkdWxlLnBocA" width="100%" height="1500px" allowfullscreen loading="lazy" style="border:none;"></iframe>
</div>

<script>
    function reloadIframe() {
        const iframe = document.getElementById('liveFrame');
        const src = iframe.src.split('?')[0];
        const originalPath = "u=L3NjaGVkdWxlLnBocA";
        const cacheBuster = "cb=" + new Date().getTime();
        iframe.src = `${src}?${originalPath}&${cacheBuster}`;
    }

    function updateIframeSize() {
        const iframe = document.getElementById('liveFrame');
        const newHeight = document.getElementById('iframeHeight').value;
        const newWidth = document.getElementById('iframeWidth').value;
        iframe.style.height = newHeight + 'px';
        iframe.style.width = newWidth;
    }
</script>

{{--<script type='text/javascript' src='///pl26587646.profitableratecpm.com/9f/45/99/9f4599940e2778b83b86ab9f9df533ae.js'></script>--}}
{{--<script data-cfasync="false" async type="text/javascript" src="//id.hybridssteng.com/r6MdeFtteNr8h/113434"></script>--}}
{{--<script>(function(s,u,z,p){s.src=u,s.setAttribute('data-zone',z),p.appendChild(s);})(document.createElement('script'),'https://bvtpk.com/tag.min.js',9305824,document.body||document.documentElement)</script>--}}
{{--<script src="https://fpyf8.com/88/tag.min.js" data-zone="146319" async data-cfasync="false"></script>--}}
</body>
</html>
