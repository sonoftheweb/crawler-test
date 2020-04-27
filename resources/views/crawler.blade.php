<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Web Crawler (Laravel)</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: #636b6f;
                font-weight: 200;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="flex-center position-ref @if (!isset($response))full-height @endif ">
            <div class="content">
                <div class="title m-b-md">
                    Web Crawler
                </div>

                <div>
                    <form method="post" action="/">
                        @csrf
                        <div class="form-group">
                            <input type="text" name="website" class="form-control form-control-lg" value="http://agencyanalytics.com/" placeholder="Enter web address">
                        </div>
                        <button type="submit" class="btn btn-primary">Begin</button>
                    </form>
                </div>
            </div>
        </div>
        @if (isset($response))
            <div class="row">
                <div class="col-md-6 offset-md-3">
                    <h3 class="mt-5 text-center">Crawl Results</h3>
                    <table class="table">
                        <thead class="thead-dark">
                        <tr>
                            <th scope="col">Domain</th>
                            <th scope="col">Path</th>
                            <th scope="col">Load Time</th>
                            <th scope="col">Status Code</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($response['crawled_domain']->pages as $page)
                            <tr>
                                <td>{{ $response['crawled_domain']->domain }}</td>
                                <td>{{ $page->path }}</td>
                                <td>{{ $page->parse_time }} s</td>
                                <td>{{ $page->http_code }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    </body>
</html>
