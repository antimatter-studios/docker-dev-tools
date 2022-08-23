# docker-dev-tools
A project composed of scripts which aid in the development of applications using docker to augment your system and provide a manner of useful tools to help you. 

### Installation

The `ddt` command is only globally available once your shell path is updated. To do this there run the following command from inside the cloned location:

```
cd docker-dev-tools
./bin/ddt setup install 
```

This will configure your shells '$PATH' environment variable, this will make the `ddt` command 
available in any terminal automatically.

After this process is complete, a new file `.ddt-system.json` will be written to
your $HOME directory. 

This file is important because it is containing all your
customised configuration and if not present will default to the `default.ddt-system.json` file inside the projects root folder. 

Do not attempt to edit or use the `default.ddt-system.json` file as it cannot be changed or edited otherwise future attempts to update the tools will most likely fail due to **"local changes"** in the directory as this project is most likely installed using `git clone` from the official repository on github.

After the shell `$PATH` environment variable is added to. You must close and open
a new terminal to see the effects of the installation. 

### Getting started

After installation, run `ddt` to see a full list of available tools. The most generally important tools are `ip, dns, proxy` tools. You can invoke them without any parameters to see what commands and parameters they take. For example:
`ddt ip`

## Ip Address Tool
The purpose of this tool is to create aliases for the localhost ip address on the development machine. The reason this is a good idea is that it provides a stable ip address that can be used, when wanting to refer to the actual dev machine.

Inside a machine, `localhost` or `127.0.0.1` (or `127.001` if you're l337), refers to the machines local loopback adapter. However, insider docker containers or virtual machines of any type, there is also a local loopback adapter, using the same ip addreses. So a problem emerges. If it's desired to connect back to the actual computer itself, not a virtual machines loopback, how is this possible? Since `127.0.0.1` could mean inside the virtual machine.

This tool sidesteps this problem by creating a stable, dependable ip address for the actual dev machine itself, which is unique. A good example of an ip address might be `10.254.254.254` which is the default that the tools are configured with

Then if the PHP XDebug extension connects back to `10.254.254.254` it'll reach the development machine itself, and the application sitting on the desktop listening can receive that callback. So this bypasses the problem that "localhost" is relative.

### Setting an IP Address
By default an ip address is already configured, which is 99.99% sure not used by any device anywhere on the internet (it's very unlikely). However if you want to change this, use the following command: 

```
ddt ip set 10.254.254.254
```

use this command to view what the pre-configured ip addres is

```
ddt ip get
```

to add/remove this ip address to your system, use one of these

```
ddt ip add
ddt ip remove
# this just does remove/add in a single command
ddt ip reset 
```

you can test it by using the ping command

```
ddt ip ping
```

## Dns Tool
The purpose of this tool is to provide local dns resolution of development domains
so real like domain names can be used instead of hacking around with ip addresses. This helps with making the whole system a bit more realistic as it would be if it was running in production on a real server on the internet.

To add a domain for development

```
ddt dns add-domain mycompany.develop
```

### Wildcards
All configured domains are treated as wildcards. This means if you configure `mycompany.develop`, then ANY subdomains will work, such as `api.mycompany.develop` or `this.is.another.subdomain.mycompany.develop` or `mail.mycompany.develop`

This is to make it easy to use and let you work without having to configure every single subdomain manually. Basically `*.mycompany.develop` will resolve to the ip address.

### Basic Control
To control the basic operating of the server, so you can work with the configuration you've made. Use these commands, they are self explanatory

```
ddt dns status
ddt dns start
ddt dns stop
ddt dns restart
```

Showing the status of the dns will output a table of all the registered domains and the ip addresses they point to. It looks like this (might need to adjust your page width settings):

```
$ ddt dns status
Registered domains:
------------------------------------
| Domain           | IP Address     |
------------------------------------
| hello.world      | 10.254.254.254 |
| aws.develop      | 10.254.254.254 |
| company.develop  | 10.254.254.254 |
------------------------------------
```

If you want to temporarily add/remove the dns to the system, but without restarting it, you can use these two commands. The server will continue to run but will not
be configured to serve requests

```
ddt dns enable
ddt dns disable
```

### Access Logs
To observe logs for dns resolution, to see if something is working as expected

```
ddt dns logs
ddt dns logs-f (logs + follow in the terminal)
```

### Need to trigger a simple refresh after a VPN session?
If DNS resolution fails for some reason, it might be that you used a VPN or need to just disable/enable/reload so it will come back and start working again.

```
ddt dns refresh
```

Once you have configured the ip and dns, you can ping the domain name directly and it will resolve it to the ip address that you've configured. An example would be:
```
ping api.mycompany.develop

$ ping api.mycompany.develop
PING api.mycompany.develop (10.254.254.254): 56 data bytes
64 bytes from 10.254.254.254: icmp_seq=0 ttl=64 time=19.655 ms
64 bytes from 10.254.254.254: icmp_seq=1 ttl=64 time=0.142 ms
```

Remember, just because you can resolve the dns name to an ip address, doesn't mean any software is running. This is only concerned with resolving the ip address. Use the proxy tool to run software and configure it to respond on those domains

---

WARNING 1: This tool requires the installation of a stable ip address using the ip tool. It uses that ip address to resolve dns addresses in a transparent fashion.

---

WARNING 2: VPN connections often override and interfere with dns resolvers for the purpose of controlling what connections are being made with the outside world. So it has been observed many times that whilst running a VPN service. The DNS resolver will stop functioning correctly. It's not yet possible to sidestep this limitation.

---

WARNING 3: Do not use `.dev` for any domain name, since it's an official TLD and there were some problems observed when trying to use it. Perhaps it will work in your case.

---

WARNING 3b: Do not use `.local` for any domain name, it clashes with mDNSResponder and is typically known as a bonjour or discovery domain name. If it's attempted to use a domain like `mycompany.local` then it's expected to fail. Use `.develop` instead

---

## Front-end Proxy Tool

Once a stable IP Address is configured, and DNS resolution for a domain is setup. You need to actually serve some software so you can access it. 

The front end proxy is an nginx software which listens on the docker socket for containers that start and stop. When one of those two events happens. It'll look at the environment variables for that container and search for special parameters. Those are as follows

```
VIRTUAL_HOST - The hostname to configure nginx to forward requests for
VIRTUAL_PORT - Which internal port to forward to
VIRTUAL_PATH - What path to match when knowing how to forward requests
```

### Example Docker Composer file
An example `docker-compose.yml` might look like this:
```
version: "3.4"
services:
  website:
    build:
      context: .
    networks:
      - proxy
    environment:
      - VIRTUAL_HOST=www.mycompany.com
      - VIRTUAL_PATH=^/prefix/path/service_a

  service_a:
    build:
      context: .
    networks:
      - proxy
    environment:
      - VIRTUAL_HOST=api.mycompany.com
      - VIRTUAL_PORT=3000 
      - VIRTUAL_PATH=^/prefix/path/service_a

  service_b:
    build: 
      context: .
    networks:
      - proxy
    environment:
      - VIRTUAL_HOST=api.mycompany.com
      - VIRTUAL_PATH=^/prefix/path/service_b
```

If you don't want to use docker compose, then simply pass the environment parameters to docker through the command line, using the required syntax

### Configure a network to listen on
Before you can use the proxy, you must declare what docker network it'll 
sit on and listen for events. You can do this by using this command:
```
ddt proxy add-network proxy
ddt proxy restart
```

You could call it anything you like, but this is just a simple example. This network is important, because the proxy will sit on this network and any containers attached to it, will be monitored and configured automatically. So any "service" which needs to respond to external requests, should be mounted on this network.

Backend services, which don't need to be accessed externally, could be mounted in such a way that the website is on the proxy network AND the backend network, where there are many backend services which communicate using docker container names instead of DNS. This would in effect create a "private network" for backend services, whilst still allowing frontend websites to access and call them. This topic can get quite complicated. So it's up to you to learn how to use docker networks to your advantage. 

The proxy simply sits on a network, listens for containers on that network and whether they start or stop, configures itself using those containers environment variables.

Remove is just as easily by using
```
ddt proxy remove-network proxy
ddt proxy restart
```

### Basic control
To control the basic functionality of the proxy, use one of these commands:
```
ddt proxy status
ddt proxy start
ddt proxy stop
ddt proxy restart
```

Showing the status of the proxy will output a table of all the registered networks, containers with the respective important information. It looks like this (might need to adjust your page width settings):

```
$ ddt proxy status
-------------------------------------------------------------------------------------------------------------------
| Docker Network | Container                 | Host             | Port | Path                      | Nginx Status |
-------------------------------------------------------------------------------------------------------------------
| backbone       | project_a_dev_nginx-1     | domain_a.develop | 80   |                           | passed       |
| backbone       | project_b_service_nginx-1 | domain_b.develop | 80   | ^/[a-zA-Z]{2,4}/service_b | passed       |
| ddt-proxy      | There are no containers   |                  |      |                           |              |
-------------------------------------------------------------------------------------------------------------------
```

### Access Logs
Logs are visible so you can see all the requests going through the proxy:
```
ddt proxy logs
ddt proxy logs-f
```

### Advanced

Sometimes, you need more advanced debugging, so you'd like to know the exact nginx configuration which is being used. This could be useful when needing to diagnose why a service doesn't respond as you expect, use this command to output the entire nginx configuration.
```
ddt proxy nginx-config
```

## Managing projects

There is a tool for managing projects. This is so you can orchestrate multiple projects to do things without having to worry too much about the individual projects, as long as they respect some rules. They will be orchestratable and you can do more advanced things.

The reason why a developer would want to do this, is because as projects get bigger, the knowledge the manage the entire stack the developer has to work with gets larger and more complex. As more team members become involved, the harder it becomes to get everybody up to speed with every detail of every project required to do things. Front end developers might not understand what services need to run, similarily back end developers might not understand how the user interfaces work.

The solution chosen to fix this problem, is that each project can hold it's own configuration that specifies what functionality can be orchestrated and what other projects it depends on. So this reduces the amount of knowledge needed to get up and running. Then the developer can investigate more over time, but get quite complex projects running with little effort. Additionally, developers who are already knowledgable about the system, do not have to do so much work in order to get relatively complex tasks. Less typing always equals a happy developer. But it's important the developer knows what these systems are doing. You can't use a tool if you don't understand what it's doing, otherwise you run into a difficult scenario where if something goes wrong. The developer will not know why, or what should be done.

The tool to manage projects is:
```
ddt project
```

First the developer should add a path to manage projects from. Typically developers put all their projects in one folder, or perhaps one folder per client, or one folder per environment. So the idea was to manage paths, with each subdirectory representing an individual project. It's possible to also add individual projects. However there is a management cost to this in that the developer will need to manage every individually managed project one by one. Whereas paths allow you to manage potentially as many projects as the developer has in a single directory. So paths are clearly easier to manage.

### Managing paths

The following commands can be used to manage paths
```
ddt project list-paths
# this will add every project it finds in the path '$HOME/projects' to the 'client-a' group
ddt project add-path $HOME/projects client-a
ddt project remove-path $HOME/projects
```

You can manage individual projects with the following examples
```
ddt project add-project $HOME/separate/rest-api client-b
```

### Integrating projects

Once a project is part of the system, it can be interacted with using a script runtime that will execute commands through a shell. These commands can be called from anywhere on the system. This allows the system to orchestrate actions to do more complex functionality. 

You can configure integration with the project by adding something similar as follows to the project:

File: `ddt-project.json` 
``` 
{
  "scripts": {
    "start": "docker-compose up -d",
    "stop": "docker-compose stop",
    "up": ["start"],
    "down": ["stop"],
    "reup": ["stop", "start"]
    "logs": "docker compose logs -f phpfpm",
    "composer": "docker compose run --rm phpfpm sh -c \"composer $@\"",
    "pull": "git pull",
    "push": "git push",
    "unit-tests": "./bin/run-phpunit unit",
    "integration-tests": "./bin/run-phpunit integration"
  },
  "dependencies": {
    "service-a": {
      "repo": {
        "url": "git@gitlab.host.com:user/service-a.git",
        "branch": "main"
      },
      "scripts": ["up", "reup", "push", "pull"]
    },
    "service-b": {
      "repo": {
        "url": "git@gitlab.host.com:user/service-b.git",
        "branch": "main"
      },
      "scripts": ["up", "reup", "push", "pull"],
    }
  }
}

``` 

Alternative ways to integrate are to put the previous contents into the `composer.json` or `package.json` files directly. However this method can make your project file quite large and it was determined some time ago a separate file is actually cleaner. Examples are like:

File: `composer.json` or `package.json`
```
{
  ... the other fields from your file

  "docker-dev-tools": {
    "scripts": {
      "start": "docker-compose up -d",
      "stop": "docker-compose stop",
      "up": ["start"],
      "down": ["stop"],
      "reup": ["stop", "start"]
      "logs": "docker compose logs -f phpfpm",
      "composer": "docker compose run --rm phpfpm sh -c \"composer $@\"",
      "pull": "git pull",
      "push": "git push",
      "unit-tests": "./bin/run-phpunit unit",
      "integration-tests": "./bin/run-phpunit integration"
    },
    "dependencies": {
      "service-a": {
        "repo": {
          "url": "git@gitlab.host.com:user/service-a.git",
          "branch": "main"
        },
        "scripts": ["up", "reup", "push", "pull"]
      },
      "service-b": {
        "repo": {
          "url": "git@gitlab.host.com:user/service-b.git",
          "branch": "main"
        },
        "scripts": ["up", "reup", "push", "pull"],
      }
    }
  }
}
```

The `scripts` key functions exactly as the developer might expect in npm or composer, in that it defines a list of scripts that can be execute

At first glance, it seems like a duplication of existing functionality and to some extent this is true when the script is a plain command. However, the tools provide functionality that neither npm or composer support in that each script that can be executed, can inspect their project dependencies and the underlying `script` key in each project dependency and trigger the same command on subsequent projects, causing a tree like spanning of multiple projects, calling the same command subsequently on each project that allows it. This is why it was not permitted to re-use the npm or composer functionalities. 

* SIDE NOTE: there is a plan to investigate a hybrid approach where for plain commands we can use the npm or composer script keys without this duplication and still orchestrate at the tool level. This would only work with plain commands and none of the advanced features described below, however it might make project configurations easier if it was possible to merge these together somehow

### Top level script key abilities

- Can be plain commands: These will be triggered in the project directory and by default all arguments will be appended to the end of the command given
- Can be an array: This means this will be a sequence of commands to trigger one after another and they can be nested and cyclic references prevented.
- When it is an array: Each command references a name of an actual command to trigger, it cannot be a plain command themselves. You can see examples in the "up", "down", and "reup" commands, they contain a simple array of command names, then each command name, is a plain command to trigger. 
- If a command name, inside an array, references another array, then that will also be iterated in the same fashion. So the developer can build more complex orchestrations as required.

### Dependency script key abilities

- Are much simpler than top level script keys
- Can only reference command names, nothing else
- By default, all scripts are denied, the developer must grant access to them by adding the child script to this script element according to one of the permitted ways in the section talking about Dependency script key formats

### Dependency script key formats

The script keys for dependencies can be created in one of the following ways, depending on much control the developer wants to exert

All child scripts are permitted, set the script key to true
```
dependencies: {
  "service-a": {
    "scripts": true
  }
}
```

All child scripts are denied, this is actually the default, but the developer can of course specify it explicitly if they want to
```
dependencies: {
  "service-a": {
    "scripts": false
  }
}
```

The same is equivalent:
```
dependencies: {
  "service-a": {

  }
}
```

You can specify an array of command names as a list that will be permitted.
```
dependencies: {
  "service-a": ["up", "down", "reup", "pull", "push"]
}
```

If the developer requires the most absolute control, then each script can be specified manually with it's configuration manually.
```
dependencies: {
  "service-a": {
    "up": true, 
    "down": false, 
    "reup": "renamed-reup-in-child-project", 
    "pull": true, 
    "push": true
  }
}
```

It's worth noting that the "down" script which has a value "false", is actually a superfluous way to define the same thing as not havint this key in this object, the developer can set "false", but it's better to just not have the key "down" in the first place.

The second thing that's worth noting, is that in the case of "reup", the developer has decided that in the child project, a different command other than the one named will be executed. So the developer has some flexibility here with the naming of commands, even depending on projects from other teams that might have different naming schemes.