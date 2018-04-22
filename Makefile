all: fresh build install

composer:
	composer update

fresh:
	echo fresh

install: 
	echo install
	
build:
	echo build

pretest:
	composer --ansi --no-interaction update
	php -f tests/PrepareForTest.php

paruj:
	cd src &&  php -f ParujFaktury.php && cd ..

parujnew2old:
	cd src &&  php -f ParujFakturyNew2Old.php && cd ..

test: pretest paruj parujnew2old
	

clean:
	rm -rf debian/php-flexibee-reminder 
	rm -rf debian/*.substvars debian/*.log debian/*.debhelper debian/files debian/debhelper-build-stamp
	rm -rf vendor composer.lock

deb:
	dch -i
	debuild -i -us -uc -b

.PHONY : install
	