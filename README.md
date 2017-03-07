# Collection of useful commands and techniques that have served me well.

## Centos

### Assign Static IP on adapter called 'rename3'

```
ifconfig rename3 192.168.56.10 netmask 255.255.255.0 up
```

### See if RPM can be updated

```
yum list myRPM
```

## NodeJS

## Clone an object without referencing it

```
var cloned_object = JSON.parse(JSON.stringify(original_object));
```

## Install NVM

```
curl -o- https://raw.githubusercontent.com/creationix/nvm/v0.29.0/install.sh | bash
nvm ls-remote
nvm install v4.2.2
```

### Run single Mocha test (alternatively install mocha globally and ensure it is in your path)

```
./node_modules/mocha/bin/mocha test/lib/my-test.js
```

## Ansible

### See what hosts will be affected by a playbook

```
ansible-playbook build-localhost-environment.yml --list-hosts
```

### Example with 'become user' and passed in variables

```
ansible-playbook -i inventory/hosts -u greensterRox -b --ask-pass --ask-become-pass update-localhost-to-specific.yml --extra-vars "package=myRPM service=myService version=1.2.3-10"
```


## Oracle

### Use dates in query

```
TO_DATE('20/10/2015', 'DD/MM/YYYY');

<!-- Between two dates -->
SELECT * FROM MY_TABLE WHERE MY_TIMESTAMP_FIELD > TO_DATE('19/03/2015','dd/mm/yyyy') AND MY_TIMESTAMP_FIELD < TO_DATE('20/03/2015','dd/mm/yyyy');
```

## Tomcat - Windows

### Start & Stop

```
%CATALINA_HOME%\bin\startup.bat

%CATALINA_HOME%\bin\shutdown.bat
```

## Windows List listening ports

```
netstat -a -n | find "LIST"
```

## Load SQL files into Oracle using SQL plus

```
sqlplus -S system/oracle @/home/file/SCHEMA.sql
```

## Docker

### use "--net=host" when your app needs to talk to something on your HOST aka 127.0.0.1

### Volume permissions issue fix

```
chcon -Rt svirt_sandbox_file_t /projects/Docker/SimpleWebApp/app_files/
```

### Build image

```
docker build -t centos-base:latest ./
```

### Remove stopped containers

```
docker rm $(docker ps -q -f status=exited)
```

### Remove all containers

```
docker rm -f $(docker ps -qa)
```

### Delete all unused images

```
docker images --no-trunc | grep none | awk '{print $3}' | xargs -r docker rmi
```

### Delete all images

```
docker rmi $(docker images -q)
```

### Go into non-running container (peek)

```
docker exec -ti a4083c8dbd99 /bin/bash -l
```

### Run container and enter it

```
docker run -i -t --entrypoint /bin/bash ${imageName}
```

### Run containers and link them

```
docker run -t -d --link high_curie:local_db -p 80:80 -p 443:443 -v /projects/Docker/myProject/:/myProject/ my-app

docker run -t -d --link tickets-rest-api-tickets-db:db2 -p 10022:22 -p 10025:25 -p 10080:80 -p 10443:443 docker.repo.com/app-rt

docker run -t -d --link berserk_franklin:db2 -p 10022:22 -p 10025:25 -p 10080:80 -p 10443:443 docker.repo.com/app-rt

docker run -t -d --link changecontrol-change-control-db:change-control-db -p 40443:443 -p 40022:22 -p 40080:80 -v /opt/change-control/webapp/:/var/www/vhosts/changecontrol/ -e APP_HOST="10.7.109.59"

```

### Run the oracle xe image with mount

```
docker run -d -p 7022:22 -p 7521:1521 -v /home/greensterRox/docker_mount:/home/root wnameless/oracle-xe-11g
```

## SWAP manipulation

```
swapoff
vi /etc/fstab (comment out line)
swapon
```

## Perl

### Run single test with TEST::MOST

```
clear; prove -fv -I t/lib t/lib/UnitTest/path/to/UnitTest.pm :: subName
clear; prove -fv -I t/lib t/lib/UnitTest/Pinnacle/gr/PDFRenderer.pm :: renderer_error_id
```

## Maven

### Install Maven in 2 steps

```
wget http://repos.fedorapeople.org/repos/dchen/apache-maven/epel-apache-maven.repo -O /etc/yum.repos.d/epel-apache-maven.repo
yum install apache-maven
```

### Automated tests with Thucydides

#### Single test

```
mvn clean verify -Drun.story=UpdateVacancy.story -Dwebdriver.base.url=http://greenster.co.uk -Dwebdriver.driver=firefox -DBATCH_NUMBER=6
mvn clean verify -Drun.story=story.name -Dwebdriver.base.url=http://greenster.co.uk -Dwebdriver.remote.url=http://win7-selntest:4444/wd/hub -Dwebdriver.driver=firefox -DBATCH_NUMBER=6
```

#### Single folder

```
mvn clean verify -Drun.folder=folder -Dwebdriver.base.url=http://greenster.co.uk -Dwebdriver.remote.url=http://win7-selntest:4444/wd/hub -Dwebdriver.driver=firefox -DBATCH_NUMBER=6
```

#### All tests from remote server

```
mvn clean verify -Dwebdriver.base.url=http://greenster.co.uk -Dwebdriver.remote.url=http://win7-selntest:4444/wd/hub -Dwebdriver.driver=firefox -DBATCH_NUMBER=6
```

#### All tests on local server

```
mvn clean verify -Dwebdriver.base.url=http://greenster.co.uk -Dwebdriver.driver=firefox -DBATCH_NUMBER=6
```

### Run individual Java test

```
clear; mvn -Dtest="com.greenster.integration.WF1495_GetNetworkingInformationV3InfiniteLoopTest#shouldReturnName" test
```

### Docker start with Maven

```
mvn docker:start -Ddocker.repo.db2=docker.repo.com/tickets-db -Ddocker.tag.db2=latest -Ddocker.image.db2=docker.repo.com/tickets-db:latest -Ddocker.container.name.db2=tickets-db -Ddocker.repo.tickets=docker.repo.com/tickets-rt -Ddocker.tag.tickets=latest -Ddocker.image.tickets=docker.repo.com/tickets-rt:latest -Ddocker.container.name.tickets=tickets-rt -Ddocker.host.tickets.port.insecure=10080 -Ddocker.container.tickets.port.insecure=80 -Ddocker.host.tickets.port.secure=10443 -Ddocker.container.tickets.port.secure=443 -Ddocker.host.tickets.port.ssh=10022 -Ddocker.container.tickets.port.ssh=22 -Ddocker.host.tickets.port.smtp=10025 -Ddocker.container.tickets.port.smtp=25 -Ddocker.host.db2.port=13306 -Ddocker.container.db2.port=3306
```

## Supervisord

### RESTART: send hangup signal

```
kill -HUP $pid
```

## Postgres

### Kill active sessions:

```
SELECT * FROM pg_stat_activity;
SELECT pg_terminate_backend(18174) FROM pg_stat_activity;
```

### Show all tables

```
SELECT table_schema || '.' || table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_schema NOT IN ('pg_catalog', 'information_schema');
```

### Find sequences

```
select * from information_schema.columns where column_default like '%sequence_name%';
```

## Bash

### Bash become user without bash rc

```
sudo su - myuser -s /bin/bash
```

### command not found nonsense ?? Try:
```
bash -l
```

## VirtualBox

### Mount Ubuntu shared folder in VirtualBox (Centos auto mounts to /media)

```
sudo mount -t vboxsf -o uid=$docker psUID,gid=$(id -g) GIT /opt/host
```

### enable web in ip tables

```
iptables -I INPUT -p tcp -m multiport --ports 80,443,5432 -j ACCEPT
```

### turn off ip tables

```
/etc/init.d/iptables save
/etc/init.d/iptables stop
chkconfig iptables off
```

## PROPEL (PHP ORM)

### Show generated SQL - put toString() on the end of the create() statement

```
die(var_dump(IpNetblocksQuery::create()
			->filterByPrimaryKeys($netblock_list)
			->orderByNetworkaddress(Criteria::ASC)->toString()));
```

## GIT

### Configure email / name

```
git config user.email "superman@metropolis.com"
git config user.name "Clark Kent"
```

### See which branch(es) your commit sha exists in

```
git branch -a --contains 9be430d4a7f764779814b948e9bb0a77784241ab
```

### Check diff of single file across two branches

```
git diff remotes/origin/develop remotes/origin/release/2.37.3 lib/Common/Utils.php

// ignore whitespace
git diff -w remotes/origin/release/2.37.2 remotes/origin/release/2.37.3 lib/Common/Utils.php
```

### Diff two commits

```
git diff 027b106095ff12273aa9e4d3d76789ce7e363e97 60afb8ba81ed0ad17b25c5119941b4a7b6019e1d
```

### Diff two branches/tags

```
git diff --name-only origin/release/200..origin/release/199		(compare branch with branch)

git diff --name-only origin/release/200..199.2					(compare branch with tag))

git log --pretty=oneline origin/master...origin/sprint7-branch

```

### Revert singe file back to state of last commit

```
checkout ${filename}
```

### Create a release branch (You need to do it on the master not the branch)

```

git clone git@github.com:GreensterRox/myrepo.git

git branch release/2.24

git push -u origin release/2.24
```

### Apply squashed commit

```

Check out the target branch (e.g. 'master')

git merge --squash (working branch name)

git diff --staged

git commit  -m "helpful comment"
```

### Find by SHA (commit id)

```
git show 4af7d86440f1d6cbcaa791509ba6a262bbf1cc88
```

### Apply a patch

```
// Make sure you are up to date
git fetch
git status
git log

//Apply a patch file
git am install/var/www/ocean/patch.patch
git log
git status

// reverse changes back to HEAD
git reset --hard HEAD^
git status
git log
```

### Merge single commit (cherry-pick)

```
git checkout 'from branch'

// get commit id from git log
git log

git checkout 'to branch'
git cherry-pick e6147ebe0aa9db3c0271f33ad39d371fe5116039

// works with minimal sha also:

git cherry-pick d0c638bd395
git cherry-pick 86d5414deaf
```

### Create changelog from commit id

```
git log --pretty=oneline 793a44a4..HEAD > /myChangeLog.txt
```

### Tell git to ignore changes to indexed file:

```
git update-index --assume-unchanged config/app.js

// and undo it again
git update-index --no-assume-unchanged config/app.js
```
