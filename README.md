# LDAP-IMAPExplorer
LDAP &amp; IMAP Explorer is a tool to make report and search over LDAP profiled mailboxes account.

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

<hostname> is the IMAP server (say better "popserver") which contains the account <username>.

With this tool, you can combine usual LDAP search with IMAP data such as space usage and limit, quota of mailboxes accounts.

A command line interface allow you to make scheduled report, sending mails to any recipients. You can export the reports in Excel file (thanks to PhpSpreadsheet).

## Install
- Git clone this project.
- Move ajax.

