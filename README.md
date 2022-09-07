# Geben 0.28

## About

This project is a clone of the original geben-on-emacs by fujinaka.tohru, with minimal changes applied to keep geben working. Necessary updates have included:

* support for modified debug protocol header blocks introduced by php unicode files
* support for Emacs evolutionary changes (like obsolete functions or new signatures)

The original README file remains useful if you've never used geben.

At time of writing, the code has been actively used with:

* Xdebug 2 and 3.
* PHP `5.*`, `7.*`, `8.*`

## Install

Simplest deployment is to clone under ~/.emacs.d/geben in place of the melpa geben package.

## Changes 0.27 from 0.28

* Emacs 27 / 28 compatible (removed obsolete function aliases and warnings)
* Support for newer `make-network-process` signature:
  * Added auto detection of local machine ipv4 address (defaults to eth0 interface)
  * Network interface or ip are optionally configurable with a new hook and two new variables:
  
    ```lisp
       (add-hook 'dbgp-start-hook
          (lambda ()
            (setq dbgp-listener-interface "eth0")
            (setq dbgp-listener-ipv4address [192 168 0 1])))
    ```
