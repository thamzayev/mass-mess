<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        @font-face {
            font-family: 'Helvetica';
            src: url('{!! url('/storage/fonts/Helvetica.ttf') !!}');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Helvetica';
            src: url('{!! url('/storage/fonts/Helvetica-Bold.ttf')!!}');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Helvetica';
            src: url('{!! url('/storage/fonts/Helvetica-Oblique.ttf') !!}');
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: 'Helvetica';
            src: url('{!! url('/storage/fonts/Helvetica-BoldOblique.ttf') !!}');
            font-weight: bold;
            font-style: italic;
        }
        @font-face {
            font-family: 'Times New Roman';
            src: url('{!! url('/storage/fonts/times.ttf') !!}');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Times New Roman';
            src: url('{!! url('/storage/fonts/timesbd.ttf') !!}');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Times New Roman';
            src: url('{!! url('/storage/fonts/timesi.ttf') !!}');
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: 'Times New Roman';
            src: url('{!! url('/storage/fonts/timesbi.ttf') !!}');
            font-weight: bold;
            font-style: italic;
        }
        @page {
            margin: 100px 50px 100px 50px;
        }
        body {
            font-family: Helvetica,'Times New Roman','DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
        }
        header {
            position: fixed;
            top: -80px;
            left: 0;
            right: 0;
            height: 70px;
        }

        footer {
            position: fixed;
            bottom: -80px;
            left: 0;
            right: 0;
            height: 70px;
        }

        main {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <header>{!! $headerHtml !!}</header>
    <footer>{!! $footerHtml !!}</footer>

    <main>
        {!! $bodyHtml !!}
    </main>
</body>
</html>
