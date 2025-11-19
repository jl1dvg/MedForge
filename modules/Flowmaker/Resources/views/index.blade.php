<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Flowmaker</title>
    <meta name="description" content="Flowmaker" />
    <meta name="author" content="Flowmaker" />
    <meta property="og:image" content="/og-image.png" />
    <link rel="stylesheet" href="{{ '/flowmaker/css' }}">

  </head>

  <body>
    <script>
      window.data = JSON.parse(@json($data));
    </script>

    
    
<div id="flow" data='{{ $data }}'></div>

<script src="{{ '/flowmaker/script' }}"></script>



</body>
</html>