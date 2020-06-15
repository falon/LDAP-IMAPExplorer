%global systemd (0%{?fedora} >= 18) || (0%{?rhel} >= 7)

Summary: A complete, more than an RBL Management System.
Name: LDAP-IMAPExplorer
Version: 1.0.0
Release: 1%{?dist}
Group: Networking/Mail
License: Apache-2.0
URL: https://falon.github.io/%{name}/
Source0: https://github.com/falon/%{name}/archive/master.zip
BuildArch:	noarch

# Required for all versions
Requires: httpd >= 2.4.6
Requires: mod_ssl >= 2.4.6
Requires: php >= 7.2
Requires: php-ldap >= 7.2
Requires: FalonCommon >= 0.1.3
Requires: php-gd
Requires: php-pecl-zip
#BuildRequires: composer >= 1.8.0
#Requires: remi-release >= 7.3


%if %systemd
# Required for systemd
%{?systemd_requires}
BuildRequires: systemd
%endif

%description
%{name} (specifically for Cyrus IMAP)
provides a web interface to browse your LDAP trees where your
IMAP account are defined. For each IMAP account you can see a lot
of details, such as quota, last access, IMAP folders, ACL...
You can make reports, export them to Excel and send to mail address.
This program include:
- PHPSpreadSheet https://phpspreadsheet.readthedocs.io
- Horde IMAP Client https://github.com/horde/Imap_Client
See at above projects for additional limitations or licenses.
GIT: https://github.com/falon/LDAP-IMAPExplorer

%clean
rm -rf %{buildroot}/

%prep
%autosetup -n %{name}-master


%install
# Web HTTPD conf
install -D -m0444 contrib/%{name}.conf-default %{buildroot}%{_sysconfdir}/httpd/conf.d/%{name}.conf
sed -i 's|\/var\/www\/html\/%{name}|%{_datadir}/%{name}|' %{buildroot}%{_sysconfdir}/httpd/conf.d/%{name}.conf

# LDAP-IMAPExplorer files
mkdir -p %{buildroot}%{_datadir}/%{name}
cp -a * %{buildroot}%{_datadir}/%{name}/
mv %{buildroot}%{_datadir}/%{name}/%{name}.conf-default %{buildroot}%{_sysconfdir}/%{name}.conf
sed -i 's|%{name}.conf|%{_sysconfdir}/%{name}.conf|' %{buildroot}%{_datadir}/%{name}/*.php
##Composer requirement
composer --working-dir="%{buildroot}%{_datadir}/%{name}" update
find %{buildroot}%{_datadir}/%{name}/vendor/pear-pear.horde.org -type f -print0 | xargs -0 sed -i "s@$RPM_BUILD_ROOT@@"
## Remove unnecessary files
rm %{buildroot}%{_datadir}/%{name}/_config.yml %{buildroot}%{_datadir}/%{name}/composer.*
rm -rf %{buildroot}%{_datadir}/%{name}/contrib
rm -rf %{buildroot}%{_datadir}/%{name}/vendor/phpoffice/phpspreadsheet/.git*
rm -rf %{buildroot}%{_datadir}/%{name}/vendor/maennchen/zipstream-php/.git*
## Add the tmp dir
install -d -m0700 -o apache -g root %{buildroot}%{_datadir}/%{name}/tmp

##File list
find %{buildroot}%{_datadir}/%{name} -mindepth 1 -type f -print0 | xargs -0 -L1 | grep -v \.conf$ | grep -v \.git | grep -v %{name}/LICENSE | grep -v %{name}/README\.md | sed -e "s@$RPM_BUILD_ROOT@\"@" | sed "s/$/\"/" > FILELIST


%post
case "$1" in
  2)
	echo -en "\n\n\e[33mRemember to check any change in %{_sysconfdir}/%{name}.conf.\e[39m\n\n"
  ;;
esac

%files -f FILELIST
%dir %attr(0700, apache, root) %{_datadir}/%{name}/tmp
%license %{_datadir}/%{name}/LICENSE
%doc %{_datadir}/%{name}/README.md
%config(noreplace) %{_sysconfdir}/%{name}.conf
%config(noreplace) %{_sysconfdir}/httpd/conf.d/%{name}.conf

%changelog
* Mon Jun 15 2020 Marco Favero <marco.favero@csi.it> 1.0.0-1
- Rebuild with newer modules versions for RH EL8

* Fri Jul 26 2019 Marco Favero <marco.favero@csi.it> 1.0.0-0
- First build
