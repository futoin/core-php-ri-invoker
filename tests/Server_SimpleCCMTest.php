<?php

//---
function fail( $msg='' )
{
    header( ':', true, 502 );
    die( $msg );
}

//---
function response( $req, $rspparams )
{
    header('Content-Type: application/futoin+json');
    $rsp = [
        'r' => $rspparams
    ];
    echo json_encode( $rsp, JSON_FORCE_OBJECT|JSON_UNESCAPED_UNICODE );
    die();
}

//---
function response_throw( $req, $err )
{
    header('Content-Type: application/futoin+json');
    $rsp = [
        'e' => $err
    ];
    echo json_encode( $rsp, JSON_FORCE_OBJECT|JSON_UNESCAPED_UNICODE );
    die();
}

// Check method
//---
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
{
    fail( 'Not POST' );
}

if ( !preg_match( ',^/ftn(/)?(\?.*)?$,', $_SERVER['REQUEST_URI'] ) )
{
    fail( 'Invalid URI: '.$_SERVER['REQUEST_URI'] );
}

// Parse Request
//---
parse_str( $_SERVER['QUERY_STRING'], $getprm );

if ( isset( $getprm['ftnreq'] ) )
{
    $req = base64_decode( $getprm['ftnreq'] );
}
else
{
    $req = file_get_contents('php://input');
}

$req = json_decode( $req );

if ( !$req )
{
    fail( 'Failed to parse' );
}

// Check iface
//---
$f = explode( ':', $req->f );

if ( count( $f ) !== 3 )
{
    fail( 'Invalid f='.$req->f );
}

if ( !preg_match( '/^[0-9]+\.[0-9]+$/', $f[1] ) )
{
    fail( 'Invalid version='.$f[1] );
}


switch ( $f[0] )
{
    case 'srv.test':
        //---
        switch ( $f[2] )
        {
            case 'test':
                response(
                    $req,
                    [
                        'pong' => 'PONGPONG',
                        'ping' => $req->p->ping
                    ]
                );
                break;
                
            case 'data':
                header('Content-Type: text/plain; charset=utf8');
                echo file_get_contents('php://input');
                die();
                
            case 'pingdata':
                header('Content-Type: text/plain; charset=utf8');
                echo $req->p->ping;
                die();
                
            case 'throw':
                response_throw( $req, $req->p->errtype );
                
            case 'notimpl':
                response_throw( $req, 'NotImplemented' );
                
            case 'notversion':
                response_throw( $req, 'NotSupportedVersion' );
        }

        break;
        
    case 'futoin.master.service':
        //---
        switch ( $f[2] )
        {
        }

        break;
}

// Else fail
//---
response_throw( $req, 'UnknownInterface' );