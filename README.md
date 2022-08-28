## About PolyCash
PolyCash is an open source blockchain protocol for peer to peer betting currencies.  PolyCash integrates with blockchains including Bitcoin, Litecoin, Dogecoin & [Datachain](https://github.com/datachains/datachain). The PolyCash protocol powers Betcoin, a digital currency with a novel inflation model where coins are given out to players for betting on virtual basketball games in addition to being given to miners for securing the network.

## Install PolyCash
You can try PolyCash by creating a web wallet on a public node like [https://poly.cash](https://poly.cash).  But installing PolyCash on your own computer helps decentralize our network and allows you to control your own private keys.

PolyCash runs on Apache, MySQL and PHP.  To get started quickly, we recommend installing PolyCash with docker.  You can download Docker Desktop [here](https://www.docker.com/products/docker-desktop/).

Once Docker Desktop is installed and running, open a terminal and clone the PolyCash repository:
```
git clone https://github.com/PolyCash/polycash.git
cd polycash
```

Next, build your docker container:
```
docker-compose -f docker-compose.yml up --build
```

Next, open your browser and navigate to:
```
http://localhost:8080/
```

PolyCash will automatically install and begin syncing with the network.  The synchronization step can take hours to complete. 

To start or stop PolyCash, simply open Docker Desktop, navigate to the Containers section and then use the start and stop buttons.

## Install Blockchains & Games
By default, the Betcoin cryptocurrency is installed when you install PolyCash.  You can install other PolyCash-protocol cryptocurrencies by pasting their game definitions in via the "Import" link found in the left menu.

To get the betcoin cryptocurrency in sync, you'll need to install the Datachain blockchain as a full node.  For more information on that step, check out [Datachain](https://github.com/datachains/datachain) on github.

After installing Datachain, click the "Manage Blockchains" link in PolyCash, then select Datachain -> Set RPC credentials, and then enter the RPC username and password from your datacoin.conf.  Then run PolyCash either by setting up a cron job to run the src/cron/minutely.php every minute, or by visiting Install -> "Start process in new tab".
