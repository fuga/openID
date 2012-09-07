<?php
/**
 * the format of XRDS and Identifier
 * 
 */
define('OPENID_IDP_XRDS',
'<?xml version="1.0" encoding="UTF-8"?>
<xrds:XRDS
    xmlns:xrds="xri://$xrds"
    xmlns:openid="http://openid.net/xmlns/1.0"
    xmlns="xri://$xrd*($v*2.0)">
  <XRD>
    <Service priority="0">
      <Type>http://specs.openid.net/auth/2.0/server</Type>
      <Type>http://openid.net/sreg/1.0</Type>
      <Type>http://openid.net/extensions/sreg/1.1</Type>
      <URI>%s</URI>
    </Service>
  </XRD>
</xrds:XRDS>');

define('OPENID_USER_XRDS',
'<?xml version="1.0" encoding="UTF-8"?>
<xrds:XRDS
    xmlns:xrds="xri://$xrds"
    xmlns:openid="http://openid.net/xmlns/1.0"
    xmlns="xri://$xrd*($v*2.0)">
  <XRD>
    <Service priority="0">
      <Type>http://specs.openid.net/auth/2.0/signon</Type>
      <Type>http://openid.net/signon/1.1</Type>
      <Type>http://openid.net/sreg/1.0</Type>
      <Type>http://openid.net/extensions/sreg/1.1</Type>
      <URI>%s</URI>
    </Service>
  </XRD>
</xrds:XRDS>');

define('OPENID_IDPAGE',
'<html>
<head>
 <meta http-equiv="X-XRDS-Location" content="%1$s"/>
 <link rel="openid2.provider openid.server" href="%2$s"/>
 <script type="text/javascript">
  location.href = "%3$s";
 </script>
</head>
<body>
  This is the identity page of <a href="%3$s">this server</a>.
</body>
</html>');