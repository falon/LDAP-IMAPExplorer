![Initial view](screenshot.jpg)

LDAP &amp; IMAP Explorer is a tool to make reports and searches over LDAP profiled mailboxes.

## Abstract
With this tool, you can combine usual LDAP search with IMAP data such as space usage and limit, quota of mailboxes accounts.

A command line interface allows you to make scheduled report, sending mails to any recipients. You can export the reports in Excel file (thanks to PhpSpreadsheet).

## Require
Every mailboxes account must have an LDAP entries like this:

```
dn: ..., <baseDN>
uid: <username>
mail: ...
mailAlternateAddress: ...
mailAlternateAddress: ...
mailhost: <hostname>
...
```

`<hostname>` is the IMAP server (say better "popserver") which contains the account `<username>`.

### system requirements
A version 7 of PHP is very recommended. A web browser as Apache is needed to access user interface.
- yum install php-ldap

Bootstrap modalbox is linked directly on html and as needed on modal.css.

## Install
### By RPM
If you have a yum based system, don't waste time , simply proceed as follow:
```
curl -1sLf \
  'https://dl.cloudsmith.io/public/csi/shared/cfg/setup/bash.rpm.sh' \
  | sudo -E bash
```

```
curl -1sLf \
  'https://dl.cloudsmith.io/public/csi/ldap-imapexplorer/cfg/setup/bash.rpm.sh' \
  | sudo -E bash
```
- dnf install LDAP-IMAPExplorer
- reload your Apache server
- point at `http(s)://<yourserver>/ldapimap`

Enjoy!

### By source
- enter in the DOCUMENT ROOT of your web server.
- Git clone this project.
- Install the "falon-common" shared HTML library
- Move `style.css` and `ajaxsbmt.js` in `DOCUMENT_ROOT/include` dir, if your didn't install the "falon-common" by RPM.
- Copy `LDAP-IMAPExplorer.conf-default` in `LDAP-IMAPExplorer.conf` and configure it as your need.
- `composer update`
- mkdir tmp
- chown apache tmp

## Licensing
This program includes:
- PHPSpreadSheet https://phpspreadsheet.readthedocs.io
- Horde IMAP Client https://github.com/horde/Imap_Client

See at the above projects for additional limitations or licenses on the use of this software.
